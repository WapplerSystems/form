<?php

declare(strict_types=1);

/*
 * This file is part of the WapplerSystems/form fork of typo3/cms-form.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace TYPO3\CMS\Form\WapplerSystems\EventListener;

use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\AssetCollector;
use TYPO3\CMS\Form\Domain\Model\Renderable\VariableRenderableInterface;
use TYPO3\CMS\Form\WapplerSystems\Event\AfterFormRenderedEvent;

/**
 * Feature 6 — frontend live-conditions.
 *
 * The server evaluates variants only for the current page state (initial render
 * / step change). For conditions that reference a field on the SAME page, that
 * is not enough — the field must show/hide (or become required) live while the
 * user fills the controlling field. This listener emits the variant rules of the
 * rendered form as a JSON island inside the <form> and loads the frontend module
 * that mirrors the evaluation client-side. The server stays authoritative on
 * submit (processVariants runs again); the client side is pure UX.
 *
 * Only variants that carry a supported effect are exported:
 *  - renderingOptions.enabled  -> show/hide
 *  - a NotEmpty validator       -> required toggle
 * Forms without such variants get neither island nor asset.
 */
#[AsEventListener('wapplersystems-form/inject-frontend-conditions')]
final class InjectFrontendConditions
{
    public function __construct(
        private readonly AssetCollector $assetCollector,
    ) {}

    public function __invoke(AfterFormRenderedEvent $event): void
    {
        $elements = [];
        foreach ($event->formRuntime->getFormDefinition()->getRenderablesRecursively() as $renderable) {
            if (!$renderable instanceof VariableRenderableInterface) {
                continue;
            }
            $rules = [];
            foreach ($renderable->getVariants() as $variant) {
                $condition = $variant->getCondition();
                if ($condition === '') {
                    continue;
                }
                $options = $variant->getOptions();
                $rule = ['condition' => $condition];
                $enabled = $options['renderingOptions']['enabled'] ?? null;
                if (is_bool($enabled)) {
                    $rule['enabled'] = $enabled;
                }
                if ($this->variantSetsRequired($options)) {
                    $rule['required'] = true;
                }
                // only rules with an actual client-applicable effect
                if (count($rule) > 1) {
                    $rules[] = $rule;
                }
            }
            if ($rules !== []) {
                $elements[$renderable->getIdentifier()] = $rules;
            }
        }

        if ($elements === []) {
            return;
        }

        $json = json_encode(
            ['elements' => $elements],
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
        $island = '<script type="application/json" data-wsform-conditions="1">' . $json . '</script>';

        $event->renderedContent = $this->insertIntoForm($event->renderedContent, $island);

        $this->assetCollector->addJavaScript(
            'wapplersystems-form-conditions',
            'EXT:form/Resources/Public/JavaScript/frontend/form-conditions.js'
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function variantSetsRequired(array $options): bool
    {
        foreach ($options['validators'] ?? [] as $validator) {
            if (is_array($validator) && ($validator['identifier'] ?? '') === 'NotEmpty') {
                return true;
            }
        }
        return false;
    }

    /**
     * Insert $island right after the opening <form …> tag so the frontend module
     * can scope it via island.closest('form'). Falls back to appending.
     */
    private function insertIntoForm(string $content, string $island): string
    {
        $pos = stripos($content, '<form');
        if ($pos === false) {
            return $content . $island;
        }
        $tagEnd = strpos($content, '>', $pos);
        if ($tagEnd === false) {
            return $content . $island;
        }
        return substr($content, 0, $tagEnd + 1) . $island . substr($content, $tagEnd + 1);
    }
}
