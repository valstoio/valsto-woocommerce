/*!
 * valsto pay button
 * JavaScript integration for Valsto's platform
 * @version 1.0.0 - 2016-03-01
 * @author Omar Yepez <https://www.valsto.com>
 */
"use strict";
var vPaymentButton = vPaymentButton || (function(){

	// private attrs
    var _attrs = {}; 
	var callback;
    var currency = "USD" 
	var styleClass = "vpayment-button";
	var valstoHome = "https://staging.valsto.com/vpayment/initSession";
	var sandboxHome = "https://staging.valsto.com/vpayment/initSession";
	var imageSRC = "https://dev.valsto.com/wooc/wp-content/plugins/valsto-vpayment-button/assets/images/pay_lw_120x35_yb1.png";		
	var buttonCaption = "";
	var cssButtonStyles = "\
		background-color: transparent;\
		background-image: url("+ imageSRC + ");\
		background-position: 1px center;\
		background-repeat: no-repeat;\
		border: medium none;\
		height: 35px;\
		cursor: pointer;\
		vertical-align: middle;\
		width: 125px;\
		box-shadow: 0 0px 0 rgba(0, 0, 0, 0.3) inset;\
	";
	
	var cssOverlayStyles = "\
		display: none;\
		position: absolute;\
		top: 0%;\
		left: 0%;\
		width: 100%;\
		height: 1000%;\
		background-color: black;\
		z-index:1001;\
		-moz-opacity: 0.8;\
		opacity:.80;\
		filter: alpha(opacity=80);\
	";
	
	var cssDialogStyles = "\
		display: none;\
		position: absolute;\
		top: 25%;\
		left: 25%;\
		width: 50%;\
		height: 400px;\
		padding: 16px;\
		border: 16px solid #f4f8f9;\
		background-color: white;\
		z-index:1002;\
		overflow: hidden;\
	";
	
	var cssIframeStyles = "\
		width:100%;\
		height:100%;\
		border:0px\
	";
	
	var cssButtonCloseDialogStyles = "\
		color: #5c6873;\
		display: inline-block;\
		font-family: 'Helvetica Neue',Helvetica,Arial,sans-serif;\
		font-size: 36px;\
		position: absolute;\
		right: 20px;\
		cursor:pointer;\
		top: 1px;\
	";
	
	/**
	 * Build the payment buttom
	 */
	var buildButtons = function(){
		var x = document.getElementsByClassName(styleClass);
		var i;
		for (i = 0; i < x.length; i++) {
			buildForm(x[i], i + 1);
		}
	};
	
	/**
	 * Build the payment form.
	 */
	var buildForm = function(el, index){
		var frm = document.createElement("form");
		var proxy = el.getAttribute('data-vproxy');

		if(proxy != undefined || proxy != ""){
			frm.action = proxy;
		}else{
			frm.action = _attrs.sandbox == undefined || _attrs.sandbox === false  ? valstoHome: sandboxHome;
		}
				
		frm.target = "valstoDialogIframe";
		frm.method = "POST";
		frm.id = "vapp-button-form-" + index;
		buildDefaultFormFields(frm, el);
		buildFormFields(frm, el);
		buildItemsFormFields(frm, el);
		buildButton(frm);
		buildDialog();
		
		if(el.getAttribute('data-auto-open') === "true"){
			openDialog();
		}
		
		el.appendChild(frm);
	};
	
	/**
	 * Build the html form fields.
	 */
	var buildFormFields = function(frm, el){
		var fields = ["data-tax", "data-shipping","data-shipping_postcode",
			"data-shipping_city","data-shipping_address_1","data-shipping_address_2",
			"data-shipping_state","data-shipping_country","data-vproxy"];
		
		for (var i = 0; i < fields.length; i++) {
			
			var attr = fields[i];
			var attrVal = el.getAttribute(attr);
			
			if(attrVal == null || attrVal == undefined){
				attrVal = 0;
			}
			
			if(fields[i] == "data-vproxy"){
				attr == "data-vproxy-endpoint";
				attrVal = _attrs.sandbox === undefined || _attrs.sandbox === false  ? valstoHome: sandboxHome;
			}
			
			addInput(frm, attr, attrVal, "hidden");
		}		
	};
	
	/**
	 * Build the default form fields.
	 */
	var buildDefaultFormFields = function(frm, el){
		var fields = {"data-merchant": _attrs.merchant, "data-api-key": _attrs.apiKey, 'data-currency-code': _attrs.currencyCode, 'referer': window.location.href || document.URL };

		for(var key in fields){

			var attr = key;
			var attrVal = el.getAttribute(attr);
			
			if(attrVal == null || attrVal == undefined){
				attrVal = fields[key];
			}
			
			addInput(frm, attr, attrVal, "hidden");
		}		
	};
	
	/**
	 * Build the form for each item in the shoping cart.
	 */
	var buildItemsFormFields = function(frm, el){
		var fields = ["data-item-ammount-", "data-item-quantity-"];
		var i = 1;
		var attr = "data-item-" + i;
		var attrVal = el.getAttribute(attr);
		while (attrVal != null && attrVal != undefined) {
			
			addInput(frm, "item_" + i, attrVal, "hidden");
			
			for (var j = 0; j < fields.length; j++) {
				attr = fields[j] + i;
				attrVal = el.getAttribute(attr);
				
				if(attrVal == null || attrVal == undefined){
					if(fields[j] == "data-item-quantity-"){
						attrVal = 1;
					}else{
						attrVal = 0;
					}
				}
				
				addInput(frm, attr, attrVal, "hidden");
				
			}
						
			attr = "data-item-" + i;
			i++;
			attrVal = el.getAttribute("data-item-" + i);
		}
	};
	
	/**
	 * Created a new input form field.
	 */
	var addInput = function(frm, name, value, type){
		var inp = document.createElement("input");
		inp.name = name.replace("data-","").replace(/-/gi,"_");
		inp.value = value;
		inp.type = type;
		frm.appendChild(inp);
	};
		
	/**
	 * Build the vPayment Button.
	 */
	var buildButton = function(el){
		var btn = document.createElement("button");
		btn.innerHTML = buttonCaption;
		btn.type = "submit";
		btn.addEventListener("click", function(){
			openDialog();
		});
		btn.style.cssText = cssButtonStyles;
		el.appendChild(btn);
	};
	
	/**
	 * Build the vPayment Dialog
	 */
	var buildDialog = function(){
		var dialog = document.getElementById('valstoDialogForm');
		if(!dialog){
			dialog = document.createElement("div");
			dialog.id='valstoDialogForm';
			dialog.className += ' valsto-dialog-content';
			dialog.style.cssText = cssDialogStyles;
			var closeButton = document.createElement("span");
			closeButton.innerHTML = 'x';
			closeButton.style.cssText = cssButtonCloseDialogStyles;
			closeButton.addEventListener("click", function(e){
				closeDialog(e);
			});
			dialog.appendChild(closeButton);
			var iframe = document.createElement("iframe");
			iframe.id="valstoDialogIframe";
			iframe.name="valstoDialogIframe";
			iframe.style.cssText = cssIframeStyles;
			dialog.appendChild(iframe);
			buildDialogOverlay();
			document.getElementsByTagName('body')[0].appendChild(dialog);
		}
	};
	
	/**
	 * Build the overlay of the dialog.
	 */
	var buildDialogOverlay = function(){
		var overlay = document.getElementById('valstoOverlay');
		if(!overlay){
			overlay = document.createElement("div");
			overlay.id='valstoOverlay';
			overlay.className += ' valsto-overlay';
			overlay.style.cssText = cssOverlayStyles;
			document.getElementsByTagName('body')[0].appendChild(overlay);
		}
	};
	
	/**
	 * Open the dialog.
	 */
	var openDialog = function(force){
		if(force === true || _attrs.beforeOpen === undefined){
			window.location.hash = '#';
			document.getElementById('valstoDialogForm').style.display='block';
			document.getElementById('valstoOverlay').style.display='block';
			window.location.hash = '#valstoDialogForm';
		}else{
			_attrs.beforeOpen();
		}
	};
	
	/**
	 * Close the dialog.
	 */
	var closeDialog = function(e){
		var iframe = document.getElementById('valstoDialogIframe');
		iframe.contentWindow.location = 'about:blank';
		document.getElementById('valstoDialogForm').style.display='none';
		document.getElementById('valstoOverlay').style.display='none';
		window.location.hash = e.target.id;
	};

	/**
	 * return the vPaymentButton Object.
	 */
    return {
    	
    	////////////////////////////////
    	
    	/**
    	 * Initiliaze the vPaymentButton.
    	 */
        init : function(attrs) {
            _attrs = attrs;
            return this.build;
        },
		
        /**
         * Build the vPaymentButton
         */
        build : function() {	
			buildButtons();
			return vPaymentButton;
        },
		
        /**
         * Show the dialog.
         */
		open : function(){
			openDialog(true)
		},
    };
}());
