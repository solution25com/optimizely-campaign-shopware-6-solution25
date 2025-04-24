<?php declare(strict_types=1);

namespace OptimizelyCampaign;

use OptimizelyCampaign\Service\SetupService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class OptimizelyCampaign extends Plugin
{
    const PLUGIN_CONFIG_ACTIVE = 'OptimizelyCampaign.config.active';
    const PLUGIN_CONFIG_OPTIVO_OPT_IN_ID = 'OptimizelyCampaign.config.optimizelyOptInId';
    const PLUGIN_CONFIG_OPTIVO_AUTH_CODE = 'OptimizelyCampaign.config.optimizelyAuthCode';
    const PLUGIN_CONFIG_RUN_EXPORT = 'OptimizelyCampaign.config.runExport';
    const PLUGIN_CONFIG_EXPORT_NAME = 'OptimizelyCampaign.config.exportName';
    //const PLUGIN_CONFIG_EXPORT_LINK = 'OptimizelyCampaign.config.exportLink';
    const PLUGIN_CONFIG_SFTP_USERNAME = 'OptimizelyCampaign.config.sftpUsername';
    const PLUGIN_CONFIG_SFTP_PASSWORD = 'OptimizelyCampaign.config.sftpPassword';
    const PLUGIN_CONFIG_SFTP_PRIVATE_KEY = 'OptimizelyCampaign.config.sftpPrivateKey';
    const NEWSLETTER_RECIPIENT_OPTIVO_OPT_IN_ID = 'optimizelyNewsletterRecipientOptInId';

    public function install(InstallContext $installContext): void
    {
        parent::install($installContext);

        $this->buildSetupService($installContext->getContext())->install();
    }

    /**
     * @param UninstallContext $context
     * @throws \Doctrine\DBAL\DBALException
     */
    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $this->buildSetupService($context->getContext())->uninstall();
    }

    private function buildSetupService(Context $context): SetupService
    {
        return new SetupService(


            $this->container->get('product_stream.repository'),
            $this->container->get('product_stream_filter.repository'),
            $this->container->get('product_export.repository'),
            $this->container->get('sales_channel.repository'),
            $this->container->get('system_config.repository'),
            $this->container->get('snippet_set.repository'),
            $this->container->get('mail_template_type.repository'),
            $this->container->get('mail_template.repository'),
            $this->container->get('Doctrine\DBAL\Connection'),
            $context
        );
    }
}