CREATE TABLE sys_refindex (
	# EXT:form BE module related DatabaseService needs this index for "form usage count" lookups
	# @todo: Solve differently somehow. It is essentially needed because not all form.yaml
	#        are FAL resources, but can be provided by extensions, too. See the registered
	#        softref parser for more details, too.
	KEY lookup_string (ref_string(191)),
	# Prevent full table scan for queries like "WHERE softref_key='formPersistenceIdentifier' and ref_uid > 0"
	KEY idx_softref_key (softref_key,ref_uid)
);

#
# WapplerSystems fork: validation-failure logging table (opt-in per form).
# Used by the RecordValidationFailures listener to record one row per
# validation error so editors can analyze drop-off and friction points.
# Contains NO submitted user values — only form/element identifiers,
# error codes, the (already translated) error message, and an HMAC of
# the FormSession identifier for cross-submission aggregation.
#
#
# WapplerSystems fork: column for the CleanupValidationLogTask scheduler task
# (configured via TCA in Configuration/TCA/Overrides/scheduler_form_cleanup_validation_log_task.php).
# Only one column is needed; further task settings reuse standard scheduler fields.
#
CREATE TABLE tx_scheduler_task (
	tx_form_retention_days SMALLINT(5) UNSIGNED DEFAULT 90 NOT NULL
);

CREATE TABLE tx_form_validation_log (
	uid                INT(11) UNSIGNED        NOT NULL AUTO_INCREMENT,
	crdate             INT(11) UNSIGNED        DEFAULT 0 NOT NULL,
	form_identifier    VARCHAR(100)            DEFAULT '' NOT NULL,
	page_uid           INT(11) UNSIGNED        DEFAULT 0 NOT NULL,
	language_uid       INT(11)                 DEFAULT 0 NOT NULL,
	element_identifier VARCHAR(100)            DEFAULT '' NOT NULL,
	property_path      VARCHAR(200)            DEFAULT '' NOT NULL,
	error_code         BIGINT(20) UNSIGNED     DEFAULT 0 NOT NULL,
	error_message      VARCHAR(500)            DEFAULT '' NOT NULL,
	page_index         SMALLINT(5) UNSIGNED    DEFAULT 0 NOT NULL,
	session_hash       VARCHAR(64)             DEFAULT '' NOT NULL,
	PRIMARY KEY (uid),
	KEY idx_form (form_identifier,crdate),
	KEY idx_session (session_hash),
	KEY idx_crdate (crdate)
);

#
# WapplerSystems fork: outgoing-mail log (see Classes/EventListener/RecordMailDeliveries.php).
# One row per notification mail an Email finisher tries to send, so the question
# "did the inquiry actually go out?" has an answer at all.
#
# The row is opened BEFORE the finisher runs and closed when its outcome is known,
# which is what makes a configuration error (missing senderAddress — thrown before
# any mail-specific event exists) and a hard abort (OOM, timeout, SIGKILL) visible
# instead of silently absent.
#
# Privacy: the recipient/subject/sender columns are only filled for forms that opted
# in, and `recipient_mode` records which policy produced `recipients` so old rows stay
# interpretable after a policy change. NEVER stored: message body, submitted form
# values, CC/BCC, attachment filenames, IP, user agent.
#
CREATE TABLE tx_form_mail_log (
	uid                 INT(11) UNSIGNED     NOT NULL AUTO_INCREMENT,
	crdate              INT(11) UNSIGNED     DEFAULT 0 NOT NULL,
	tstamp              INT(11) UNSIGNED     DEFAULT 0 NOT NULL,
	status              TINYINT(3) UNSIGNED  DEFAULT 0 NOT NULL,
	submission_id       VARCHAR(32)          DEFAULT '' NOT NULL,
	form_identifier     VARCHAR(100)         DEFAULT '' NOT NULL,
	finisher_identifier VARCHAR(100)         DEFAULT '' NOT NULL,
	finisher_class      VARCHAR(190)         DEFAULT '' NOT NULL,
	site_identifier     VARCHAR(100)         DEFAULT '' NOT NULL,
	page_uid            INT(11) UNSIGNED     DEFAULT 0 NOT NULL,
	language_uid        INT(11)              DEFAULT 0 NOT NULL,
	recipient_mode      VARCHAR(10)          DEFAULT '' NOT NULL,
	recipients          VARCHAR(255)         DEFAULT '' NOT NULL,
	recipient_count     SMALLINT(5) UNSIGNED DEFAULT 0 NOT NULL,
	subject             VARCHAR(255)         DEFAULT '' NOT NULL,
	sender              VARCHAR(255)         DEFAULT '' NOT NULL,
	reply_to            VARCHAR(255)         DEFAULT '' NOT NULL,
	attachment_count    SMALLINT(5) UNSIGNED DEFAULT 0 NOT NULL,
	transport           VARCHAR(50)          DEFAULT '' NOT NULL,
	message_id          VARCHAR(190)         DEFAULT '' NOT NULL,
	error_code          BIGINT(20) UNSIGNED  DEFAULT 0 NOT NULL,
	error_class         VARCHAR(190)         DEFAULT '' NOT NULL,
	error_message       VARCHAR(500)         DEFAULT '' NOT NULL,
	PRIMARY KEY (uid),
	KEY idx_crdate (crdate),
	KEY idx_form (form_identifier,crdate),
	KEY idx_status (status,crdate),
	KEY idx_submission (submission_id)
);

#
# WapplerSystems fork: consent log (see Classes/EventListener/RecordConsents.php).
# One row per consent checkbox per submission.
#
# Art. 7(1) GDPR asks the controller to be able to demonstrate that the data
# subject consented. For a form whose only finisher is an Email one, the
# notification mail is the sole trace of a submission — a mailbox is not an
# evidence store, and it cannot say WHICH wording was shown. This table can:
# `text_hash` points at tx_form_consent_text, so a later edit of the consent
# text does not rewrite what past visitors agreed to.
#
# `subject` holds one identifying value from the submission (usually the e-mail
# address) so a record can be produced for a named person. It is the only
# personal datum here, it is only filled when the form names a subject field,
# and CleanupConsentLogTask prunes it — see the task for why its default
# retention is years rather than the 90 days of the mail log.
#
CREATE TABLE tx_form_consent_log (
	uid                INT(11) UNSIGNED    NOT NULL AUTO_INCREMENT,
	crdate             INT(11) UNSIGNED    DEFAULT 0 NOT NULL,
	submission_id      VARCHAR(32)         DEFAULT '' NOT NULL,
	form_identifier    VARCHAR(100)        DEFAULT '' NOT NULL,
	element_identifier VARCHAR(100)        DEFAULT '' NOT NULL,
	consent_key        VARCHAR(100)        DEFAULT '' NOT NULL,
	given              TINYINT(1) UNSIGNED DEFAULT 0 NOT NULL,
	text_hash          VARCHAR(64)         DEFAULT '' NOT NULL,
	subject            VARCHAR(255)        DEFAULT '' NOT NULL,
	subject_field      VARCHAR(100)        DEFAULT '' NOT NULL,
	site_identifier    VARCHAR(100)        DEFAULT '' NOT NULL,
	page_uid           INT(11) UNSIGNED    DEFAULT 0 NOT NULL,
	language_uid       INT(11)             DEFAULT 0 NOT NULL,
	PRIMARY KEY (uid),
	KEY idx_form (form_identifier,crdate),
	KEY idx_submission (submission_id),
	KEY idx_subject (subject(100)),
	KEY idx_crdate (crdate)
);

#
# WapplerSystems fork: the distinct consent wordings ever shown, addressed by
# the SHA-256 of the text. Normalised out of tx_form_consent_log because the
# same paragraph repeats on every single submission, and because "which
# versions have we ever used" then becomes one query instead of a GROUP BY over
# the whole log.
#
# Rows are written once and never updated; `last_seen` only moves forward.
#
CREATE TABLE tx_form_consent_text (
	uid          INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	crdate       INT(11) UNSIGNED DEFAULT 0 NOT NULL,
	last_seen    INT(11) UNSIGNED DEFAULT 0 NOT NULL,
	text_hash    VARCHAR(64)      DEFAULT '' NOT NULL,
	language_uid INT(11)          DEFAULT 0 NOT NULL,
	consent_text TEXT,
	PRIMARY KEY (uid),
	UNIQUE KEY uniq_hash (text_hash)
);
