<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\AllowedMethodsTrait;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Form\Domain\DTO\SearchCriteria;
use TYPO3\CMS\Form\Mvc\Configuration\ConfigurationManagerInterface as FormConfigurationManagerInterface;
use TYPO3\CMS\Form\Mvc\Persistence\FormPersistenceManagerInterface;
use TYPO3\CMS\Form\Service\FormKillSwitch;

/**
 * The switchboard: which forms are currently accepting anything, and the one
 * click that changes it.
 *
 * It lives in the form log module rather than in the form builder on purpose.
 * During an incident the sequence is: the validation statistics show the
 * flood, and the switch is the next thing you want — one view away, not in a
 * different branch of the module menu. The form builder is where forms are
 * designed, which is a different job on a different day.
 *
 * No extra admin check beyond module access. The module already sits in the
 * Administration section; and an editor whose contact form is being flooded
 * should be able to stop it rather than having to find an administrator first.
 *
 * @internal not part of public TYPO3 Core API
 */
class KillSwitchController extends AbstractFormLogController
{
    use AllowedMethodsTrait;

    /**
     * Offered durations, in minutes. 0 means "until somebody lifts it".
     *
     * @var list<int>
     */
    private const DURATIONS = [0, 15, 30, 60, 120, 360, 720, 1440];

    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        ComponentFactory $componentFactory,
        IconFactory $iconFactory,
        protected readonly FormKillSwitch $killSwitch,
        protected readonly FormPersistenceManagerInterface $formPersistenceManager,
        protected readonly FormConfigurationManagerInterface $formConfigurationManager,
        protected readonly SiteFinder $siteFinder,
        protected readonly ExtensionConfiguration $extensionConfiguration,
    ) {
        parent::__construct($moduleTemplateFactory, $componentFactory, $iconFactory);
    }

    protected function indexAction(): ResponseInterface
    {
        $state = $this->killSwitch->getState();

        $moduleTemplate = $this->createModuleTemplate($this->request, 'killSwitch.headline');
        $moduleTemplate->assignMultiple([
            'state' => $state,
            'forms' => $this->forms($state['forms']),
            'sites' => $this->sites($state['sites']),
            'durations' => $this->durations(),
            'actionTarget' => $this->buildFilterFormTarget('update'),
            'autoDisableAvailable' => $this->autoDisableEnabled(),
        ]);

        return $moduleTemplate->renderResponse('Backend/KillSwitch/Index');
    }

    /**
     * One POST endpoint for every switch on the page.
     *
     * POST, not GET: a backend route URI carries a request token that a GET
     * form would drop (see AbstractFormLogController::buildFilterFormTarget()),
     * and more importantly a state change must not sit behind a URL that a
     * browser, a prefetcher or a bookmark can replay.
     *
     * @param array<string, mixed> $autoDisable
     */
    protected function updateAction(
        string $scope = '',
        string $identifier = '',
        string $operation = '',
        string $message = '',
        int $duration = 0,
        array $autoDisable = [],
    ): ResponseInterface {
        // Rejects the action outright unless it really was a POST, so the
        // state change cannot be reached by a link, a prefetch or a bookmark.
        $this->assertAllowedHttpMethod($this->request, 'POST');

        if ($operation === 'autoDisable') {
            $this->killSwitch->setAutoDisableSettings(
                (int)($autoDisable['threshold'] ?? FormKillSwitch::AUTO_DISABLE_DEFAULTS['threshold']),
                (int)($autoDisable['windowMinutes'] ?? FormKillSwitch::AUTO_DISABLE_DEFAULTS['windowMinutes']),
                (int)($autoDisable['durationMinutes'] ?? FormKillSwitch::AUTO_DISABLE_DEFAULTS['durationMinutes']),
                (string)($autoDisable['notify'] ?? '')
            );

            return $this->redirect('index');
        }

        $scope = in_array($scope, [FormKillSwitch::SCOPE_GLOBAL, FormKillSwitch::SCOPE_SITE, FormKillSwitch::SCOPE_FORM], true)
            ? $scope
            : '';
        if ($scope === '') {
            return $this->redirect('index');
        }

        // Only the two operations the view actually sends. An unrecognised
        // value must do nothing rather than fall through to "enable": a
        // malformed request quietly releasing a block is the wrong way for a
        // safety switch to fail.
        if ($operation === 'disable') {
            $until = $duration > 0
                ? (int)($GLOBALS['EXEC_TIME'] ?? time()) + $duration * 60
                : null;
            $this->killSwitch->disable($scope, $identifier !== '' ? $identifier : null, $this->currentUserName(), trim($message), $until);
        } elseif ($operation === 'enable') {
            $this->killSwitch->enable($scope, $identifier !== '' ? $identifier : null);
        }

        return $this->redirect('index');
    }

    /**
     * Every form of the installation, each with the block that applies to it.
     * Listing all of them rather than only the blocked ones is the point: the
     * view has to answer "is anything off?" without the reader knowing what to
     * look for.
     *
     * @param array<string, array<string, mixed>> $blocked
     * @return list<array<string, mixed>>
     */
    protected function forms(array $blocked): array
    {
        $forms = [];
        $seen = [];

        try {
            $formSettings = $this->formSettings();
            foreach ($this->formPersistenceManager->listForms($formSettings, new SearchCriteria()) as $formMetadata) {
                if (isset($seen[$formMetadata->identifier])) {
                    continue;
                }
                $seen[$formMetadata->identifier] = true;
                $forms[] = [
                    'identifier' => $formMetadata->identifier,
                    'name' => $formMetadata->name,
                    'entry' => $blocked[$formMetadata->identifier] ?? null,
                ];
            }
        } catch (\Throwable) {
            // A storage that cannot be read must not cost the switch: the
            // blocked entries below are the part that has to stay reachable.
            $forms = [];
        }

        // A form that was blocked and has since been deleted or renamed would
        // otherwise be invisible here while its entry keeps blocking anything
        // that takes its identifier back.
        foreach ($blocked as $identifier => $entry) {
            if (isset($seen[$identifier])) {
                continue;
            }
            $forms[] = ['identifier' => $identifier, 'name' => $identifier, 'entry' => $entry, 'orphaned' => true];
        }

        return $forms;
    }

    /**
     * @param array<string, array<string, mixed>> $blocked
     * @return list<array<string, mixed>>
     */
    protected function sites(array $blocked): array
    {
        $sites = [];
        foreach ($this->siteFinder->getAllSites() as $site) {
            $sites[] = [
                'identifier' => $site->getIdentifier(),
                'name' => (string)($site->getConfiguration()['websiteTitle'] ?? $site->getIdentifier()),
                'entry' => $blocked[$site->getIdentifier()] ?? null,
            ];
        }

        return $sites;
    }

    /**
     * @return array<int, string>
     */
    protected function durations(): array
    {
        $options = [];
        foreach (self::DURATIONS as $minutes) {
            $options[$minutes] = $minutes === 0
                ? $this->translate('killSwitch.duration.untilLifted')
                : $this->formatDuration($minutes);
        }

        return $options;
    }

    protected function formatDuration(int $minutes): string
    {
        if ($minutes % 1440 === 0) {
            return sprintf('%d d', intdiv($minutes, 1440));
        }
        if ($minutes % 60 === 0) {
            return sprintf('%d h', intdiv($minutes, 60));
        }

        return sprintf('%d min', $minutes);
    }

    /**
     * @return array<string, mixed>
     */
    protected function formSettings(): array
    {
        $typoScriptSettings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'form'
        );

        return $this->formConfigurationManager->getYamlConfiguration($typoScriptSettings, false);
    }

    protected function currentUserName(): string
    {
        return (string)($GLOBALS['BE_USER']->user['username'] ?? 'backend');
    }

    protected function autoDisableEnabled(): bool
    {
        try {
            return (bool)$this->extensionConfiguration->get('form', 'featureFormAutoDisable');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return false;
        }
    }
}
