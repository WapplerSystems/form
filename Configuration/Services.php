<?php

declare(strict_types=1);

namespace TYPO3\CMS\Form;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\DependencyInjection\PublicServicePass;
use TYPO3\CMS\Form\ConfigurationModuleProvider\FormYamlProvider;
use TYPO3\CMS\Form\Domain\Finishers\FinisherInterface;
use TYPO3\CMS\Form\Upgrades\FileFormsToDatabaseUpgradeWizard;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;
use TYPO3\CMS\Lowlevel\ConfigurationModuleProvider\ProviderRegistry;

return static function (ContainerConfigurator $container, ContainerBuilder $containerBuilder) {
    $containerBuilder->registerForAutoconfiguration(FinisherInterface::class)->addTag('form.finisher');
    $containerBuilder->addCompilerPass(new PublicServicePass('form.finisher', true));

    // The upgrade wizard interfaces are part of EXT:install on v13 (Core on
    // v14). EXT:install is not a dependency of this extension, so the wizard is
    // only registered when it is actually there - autoconfiguring it from
    // Services.yaml would break the container build without it.
    if (interface_exists(UpgradeWizardInterface::class)) {
        $container->services()->defaults()->autowire()->autoconfigure()->public()
            ->set(FileFormsToDatabaseUpgradeWizard::class);
    }

    if ($containerBuilder->hasDefinition(ProviderRegistry::class)) {
        $container->services()->defaults()->autowire()->autoconfigure()->public()
            ->set('lowlevel.configuration.module.provider.formyamlconfiguration')
            ->class(FormYamlProvider::class)
            ->tag(
                'lowlevel.configuration.module.provider',
                [
                    'identifier' => 'formYamlConfiguration',
                    'after' => 'eventListeners',
                ]
            );
    }
};
