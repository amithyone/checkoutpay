( function () {
    if ( typeof window.wc === 'undefined' || ! window.wc.wcBlocksRegistry || ! window.wc.wcSettings ) {
        return;
    }

    var settings = window.wc.wcSettings.getSetting( 'checkoutpay_data', {} );
    var decodeEntities = window.wp && window.wp.htmlEntities
        ? window.wp.htmlEntities.decodeEntities
        : function ( value ) { return value; };

    var title = decodeEntities( settings.title || '' ) || 'CheckoutPay';
    var description = decodeEntities( settings.description || '' );

    var Label = function () {
        return window.wp.element.createElement( 'span', null, title );
    };

    var Content = function () {
        return window.wp.element.createElement(
            'div',
            { className: 'wc-copn-blocks-description' },
            description
        );
    };

    window.wc.wcBlocksRegistry.registerPaymentMethod( {
        name: 'checkoutpay',
        label: window.wp.element.createElement( Label, null ),
        content: window.wp.element.createElement( Content, null ),
        edit: window.wp.element.createElement( Content, null ),
        canMakePayment: function () {
            return true;
        },
        ariaLabel: title,
        supports: {
            features: settings.supports || [ 'products' ],
        },
    } );
} )();
