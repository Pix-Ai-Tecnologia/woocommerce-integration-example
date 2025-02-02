const { registerPaymentMethod } = window.wc?.wcBlocksRegistry || {};
const { createElement, useEffect, useState } = window.wp?.element || {};
const { getSetting, getCartEndpoint, nonce } = window.wc?.wcSettings || {};
const settings = window.wc.wcSettings.getSetting("my_pix_ai_gateway_data", {});
const PixAiPayment = (props) => {
  const [qrCode, setQrCode] = useState(null);
  const [isPaid, setIsPaid] = useState(false);
  const { onPaymentProcessing } = props.eventRegistration;

  const ajaxUrl = window.pixAiSettings?.ajax_url || "";

  console.log("ajaxUrl: ", [ajaxUrl, window.pixAiSettings]);
  console.log("settings: ", settings);

  useEffect(() => {
    const unsubscribe = onPaymentProcessing(async () => {
      if (isPaid) return;

      // Show loading message
      setQrCode("loading");

      try {
        const response = await fetch(
          getCartEndpoint() + "/checkout",
          {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              "X-WC-Store-API-Nonce": nonce,
            },
            body: JSON.stringify({
              payment_method: "my_pix_ai_gateway_data",
              billing: props.billingData,
              shipping: props.shippingData,
            }),
          }
        );

        const data = await response.json();

        console.log("data: ", data);

        if (data.result === "success" && data.qr_code) {
          setQrCode(data.qr_code); // Display the QR Code
          await waitForPaymentConfirmation(
            data.payment_identification,
            data.api_token
          );
        } else {
          console.error("Erro ao gerar QR Code:", data);
        }
      } catch (error) {
        console.error("Erro na requisição do QR Code:", JSON.stringify(error));
      }
    });

    return () => unsubscribe();
  }, [ajaxUrl, onPaymentProcessing, isPaid]);

  // Function to check payment status every 5 seconds
  const waitForPaymentConfirmation = async (orderId, token) => {
    const checkPaymentUrl = `https://manager.pixai.com.br/api/integration/payment-initiation/${orderId}`;

    const interval = setInterval(async () => {
      try {
        const res = await fetch(checkPaymentUrl, {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        });
        const result = await res.json();

        if (result.success && result.paid) {
          clearInterval(interval);
          setIsPaid(true);
          window.location.href = result.redirect_url; // Redirect to WooCommerce order page
        }
      } catch (error) {
        console.error("Erro ao verificar pagamento:", error);
      }
    }, 5000);
  };

  return createElement(
    "div",
    null,
    createElement("p", null, "Pague com Pix Ai e escaneie o QR Code abaixo:"),
    qrCode === "loading"
      ? createElement("p", null, "Gerando QR Code...")
      : qrCode
      ? createElement("img", { src: qrCode, alt: "QR Code Pix Ai" })
      : null
  );
};

// Register the payment method in WooCommerce Blocks
const Block_Gateway = {
  name: "my_pix_ai_gateway",
  label: "Pix Ai Payment",
  content: createElement(PixAiPayment, null),
  edit: createElement(PixAiPayment, null),
  canMakePayment: () => true,
  ariaLabel: "Pix Ai Payment",
  supports: { features: ["products"] },
};

// Register if WooCommerce Blocks exist
if (registerPaymentMethod) {
  registerPaymentMethod(Block_Gateway);
}

// =================

// const settings = window.wc.wcSettings.getSetting("my_pix_ai_gateway_data", {});
// const label = window.wp.i18n.__("Pix Ai Gateway", "my_pix_ai_gateway");

// // // import { registerPaymentMethod } from "@woocommerce/blocks-registry";
// // import { useEffect, useState } from "@wordpress/element";

// const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
// const { useEffect, useState } = window.wp.element;
// const { getSetting } = window.wc?.wcSettings || {}; // Ensure WooCommerce settings exist
// const { createElement } = window.wp?.element || {};

// const ContentEdit = (props) => {
//   const [qrCode, setQrCode] = useState(null);
//   const [isLoading, setIsLoading] = useState(false);
//   const { onPaymentProcessing } = props.eventRegistration;

//   const ajaxUrl = getSetting ? getSetting('wc_ajax_url', '') + '&action=generate_pix_qr_code' : '';

//   useEffect(() => {
//     const unsubscribe = onPaymentProcessing(async () => {
//       setIsLoading(true);

//       // Get WooCommerce form data
//       const billingData = props.billing;
//       console.log("Billing Data:", billingData); // Debugging, remove in production

//       // Create request body for Pix Ai API
//       const formData = new URLSearchParams({
//         action: "generate_pix_qr_code",
//         amount: billingData?.total || 0, // Order amount
//         email: billingData?.billing_email || "", // Customer email
//         name: billingData?.billing_first_name || "", // Customer name
//       });

//       try {
//         const response = await fetch(ajaxUrl, {
//           method: "POST",
//           headers: { "Content-Type": "application/x-www-form-urlencoded" },
//           body: formData,
//         });

//         const data = await response.json();
//         if (data.success) {
//           setQrCode(data.data.qr_code_url);
//         } else {
//           console.error("Erro ao gerar QR Code:", data);
//         }
//       } catch (error) {
//         console.error("Erro na requisição do QR Code:", error);
//       }

//       setIsLoading(false);
//     });

//     return () => unsubscribe();
//   }, [ajaxUrl, onPaymentProcessing, props.billing]);

//   return createElement(
//     "div",
//     null,
//     createElement(
//       "p",
//       null,
//       "Clique em finalizar compra para gerar seu QR Code Pix."
//     ),
//     isLoading
//       ? createElement("p", null, "Gerando QR Code...")
//       : qrCode
//       ? createElement("img", { src: qrCode, alt: "QR Code Pix Ai" })
//       : null
//   );
// };

// // const Content = () => {
// //   return (
// //     <div>
// //       <p>{window.wp.htmlEntities.decodeEntities(settings.description || "")}</p>
// //       <img
// //         src="https://api.qrserver.com/v1/create-qr-code/?data=PixAiExample&size=200x200"
// //         alt="QR Code Pix Ai"
// //       />
// //     </div>
// //   );
// // };

// // const ContentEdit = () => {
// //   return window.wp.htmlEntities.decodeEntities(settings.description || "");
// //   // <div>
// //   //     {window.wp.htmlEntities.decodeEntities(settings.description || '')}
// //   //     <div>{window.wp.i18n.__('This is a text inside a div.', 'my_pix_ai_gateway')}</div>
// //   // </div>
// // };

// const Block_Gateway = {
//   name: "my_pix_ai_gateway",
//   label: label,
//   content: Object(window.wp.element.createElement)(ContentEdit, null),
//   edit: Object(window.wp.element.createElement)(ContentEdit, null),
//   //   content: ContentEdit,
//   //   edit: ContentEdit,
//   canMakePayment: () => true,
//   ariaLabel: label,
//   supports: {
//     features: ["products"],
//   },
// };
// window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
