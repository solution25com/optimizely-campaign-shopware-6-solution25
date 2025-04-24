import template from './sw-mail-template-detail.html.twig';
import './sw-mail-template-detail.scss';

const { Component, Mixin } = Shopware;

Component.override('sw-mail-template-detail', {
    template
});
