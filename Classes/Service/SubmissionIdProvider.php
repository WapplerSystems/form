<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Service;

/**
 * One opaque id per submission, shared by every log the fork writes.
 *
 * Extracted from MailLogRecorder once the consent log appeared: the two logs
 * answer different halves of the same question — "was the consent given?" and
 * "did the notification actually go out?" — and joining them is only possible
 * if they agree on what a submission is called. Two independently generated
 * ids would have made the pair useless for exactly the case they exist for.
 *
 * Not a hash of anything about the visitor: a random value cannot be used to
 * recognise the same person across submissions, which is deliberate. It is a
 * join key, not an identifier.
 *
 * Request-scoped by being a container singleton — a finisher chain runs within
 * one request, which is the whole lifetime the id has to survive.
 *
 * @internal not part of public TYPO3 Core API
 */
final class SubmissionIdProvider
{
    private ?string $submissionId = null;

    public function get(): string
    {
        return $this->submissionId ??= bin2hex(random_bytes(16));
    }
}
