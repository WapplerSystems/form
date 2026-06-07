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
