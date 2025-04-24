const {PluginBaseClass} = window;

export default class PreselectCookiePlugin extends PluginBaseClass {

    init() {
        const button = document.querySelector('.js-cookie-configuration-button button');
        if (button) {
            button.addEventListener('click', () => {
                this.observeModalAddition();
            });
        }
    }

    observeModalAddition() {
        const observerCallback = (mutationsList, observer) => {
            for (const mutation of mutationsList) {
                if (mutation.type === 'childList') {
                    for (const addedNode of mutation.addedNodes) {
                        if (addedNode.nodeType === 1 && addedNode.classList.contains('offcanvas-body')) {
                            let cookieGroup = addedNode.querySelector('[id="cookie_Optimizely Campaign Cookies"]')
                            let onPurchaseCookie = addedNode.querySelector("#cookie_onPurchaseOrder");
                            let onProductCookie = addedNode.querySelector("#cookie_onProductView");
                            let onAddToBasketCookie = addedNode.querySelector("#cookie_onAddToBasket");
                            if (cookieGroup && onPurchaseCookie && onProductCookie && onAddToBasketCookie) {
                                onAddToBasketCookie.checked = true;
                                onProductCookie.checked = true;
                                onPurchaseCookie.checked = true;
                                cookieGroup.checked = true;
                            }
                        }
                    }
                }
            }
        };

        let observer = new MutationObserver(observerCallback);
        const config = {
            childList: true,
            subtree: true,
        };

        observer.observe(document.body, config);
    }
}
