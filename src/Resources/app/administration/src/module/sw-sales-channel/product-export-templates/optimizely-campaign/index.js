import header from './header.csv.twig';
import body from './body.csv.twig';

Shopware.Service('exportTemplateService').registerProductExportTemplate({
    name: 'optimizely-campaign',
    translationKey: 'sw-sales-channel.detail.productComparison.templates.template-label.optimizely',
    headerTemplate: header.trim(),
    bodyTemplate: body.trim(),
    footerTemplate: '',
    fileName: 'optimizelyProducts.csv',
    encoding: 'UTF-8',
    fileFormat: 'csv',
    generateByCronjob: true,
    interval: 86400
});
