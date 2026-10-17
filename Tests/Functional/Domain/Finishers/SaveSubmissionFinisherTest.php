<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\Tests\Functional\Domain\Finishers;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface as ExtbaseConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request;
use TYPO3\CMS\Form\Domain\Factory\ArrayFormFactory;
use TYPO3\CMS\Form\Domain\Finishers\FinisherContext;
use TYPO3\CMS\Form\Domain\Finishers\SaveSubmissionFinisher;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Service\TranslationService;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SaveSubmissionFinisherTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = ['form'];

    #[Test]
    public function storesOnlyTheAnsweredFieldsWithReadableValues(): void
    {
        $row = $this->submit(['storagePid' => 'pages_7', 'label' => 'Membership']);

        self::assertSame(7, (int)$row['pid']);
        self::assertSame('test-form', $row['form_identifier']);
        self::assertSame('Membership', $row['form_label']);

        // No headline, no honeypot, no disabled field, no page.
        $content = json_decode($row['content'], true);
        self::assertSame(['name', 'state', 'birthday'], array_keys($content));
        self::assertSame('Jane', $content['name']);
        // Option label, not the key.
        self::assertSame('Bavaria', $content['state']);
        // The property mapper adds the time of day; only the date was entered.
        self::assertSame('1980-01-01', $content['birthday']);

        self::assertSame(
            ['name' => 'Name', 'state' => 'State', 'birthday' => 'Birthday'],
            json_decode($row['field_labels'], true),
        );
    }

    #[Test]
    public function storesOptionKeysWhenLabelsAreSwitchedOff(): void
    {
        $row = $this->submit(['storeOptionLabels' => false]);

        self::assertSame('BY', json_decode($row['content'], true)['state']);
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function submit(array $options): array
    {
        $request = $this->buildExtbaseRequest();
        $subject = $this->get(SaveSubmissionFinisher::class);
        $subject->setFinisherIdentifier('SaveSubmission');
        $subject->injectTranslationService($this->get(TranslationService::class));
        $subject->setOptions($options);

        $subject->execute(new FinisherContext($this->buildFormRuntime($request), $request));

        $row = $this->getConnectionPool()->getQueryBuilderForTable('tx_form_submission')
            ->select('*')
            ->from('tx_form_submission')
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($row);
        return $row;
    }

    private function buildExtbaseRequest(): Request
    {
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);
        $this->get(ExtbaseConfigurationManagerInterface::class)->setRequest(
            (new ServerRequest())
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
                ->withAttribute('frontend.typoscript', $frontendTypoScript)
                ->withAttribute('language', new SiteLanguage(0, 'en_US.UTF-8', new Uri('/'), []))
        );

        $frontendUser = new FrontendUserAuthentication();
        $frontendUser->initializeUserSessionManager();
        $serverRequest = (new ServerRequest())
            ->withAttribute('extbase', new ExtbaseRequestParameters())
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('language', new SiteLanguage(0, 'en_US.UTF-8', new Uri('/'), []));
        $GLOBALS['TYPO3_REQUEST'] = $serverRequest;

        return (new Request($serverRequest))->withPluginName('Formframework');
    }

    private function buildFormRuntime(Request $request): FormRuntime
    {
        $formDefinition = $this->get(ArrayFormFactory::class)->build([
            'type' => 'Form',
            'identifier' => 'test-form',
            'label' => 'Test form',
            'prototypeName' => 'standard',
            'renderables' => [
                [
                    'type' => 'Page',
                    'identifier' => 'page-1',
                    'label' => 'Page 1',
                    'renderables' => [
                        ['type' => 'StaticText', 'identifier' => 'headline', 'label' => 'About you'],
                        ['type' => 'Text', 'identifier' => 'name', 'label' => 'Name'],
                        ['type' => 'Honeypot', 'identifier' => 'xq7Kp2'],
                        [
                            'type' => 'SingleSelect',
                            'identifier' => 'state',
                            'label' => 'State',
                            'properties' => ['options' => ['BW' => 'Baden-Württemberg', 'BY' => 'Bavaria']],
                        ],
                        ['type' => 'Date', 'identifier' => 'birthday', 'label' => 'Birthday'],
                        [
                            'type' => 'Text',
                            'identifier' => 'explanation',
                            'label' => 'Explanation',
                            'renderingOptions' => ['enabled' => false],
                        ],
                    ],
                ],
            ],
        ], null, new ServerRequest());
        $formRuntime = $formDefinition->bind($request);
        $formState = $formRuntime->getFormState();
        $formState->setFormValue('headline', null);
        $formState->setFormValue('name', 'Jane');
        $formState->setFormValue('xq7Kp2', '');
        $formState->setFormValue('state', 'BY');
        $formState->setFormValue('birthday', new \DateTime('1980-01-01 14:23:05'));
        $formState->setFormValue('explanation', 'left over from a variant');
        return $formRuntime;
    }
}
