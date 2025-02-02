const { registerPaymentMethod } = window.wc?.wcBlocksRegistry || {};
const { createElement, useEffect, useState } = window.wp?.element || {};
const { getSetting, getCartEndpoint, nonce } = window.wc?.wcSettings || {};
const settings = window.wc.wcSettings.getSetting("my_pix_ai_gateway_data", {});
let isSubmitting = false;

const fetchExistingOrder = async (orderId) => {
  const orderUrl = `${window.pixAiSettings.storeApiUrl}/orders/${orderId}`;

  console.log("Fetching existing order data:", orderUrl);

  try {
    const response = await fetch(orderUrl, {
      method: "GET",
      headers: {
        "Content-Type": "application/json",
        "X-WC-Store-API-Nonce": window.pixAiSettings.nonce,
        Nonce: window.pixAiSettings.nonce,
      },
    });

    const data = await response.json();
    console.log("Order data:", data);

    if (data.payment_result?.payment_status === "success") {
      const paymentDetailsObject = Object.fromEntries(
        data.payment_result.payment_details.map((detail) => [
          detail.key,
          detail.value,
        ])
      );

      console.log("Existing order payment details:", paymentDetailsObject);

      setQrCode(paymentDetailsObject.qr_code); // Display the QR Code
      await waitForPaymentConfirmation(
        paymentDetailsObject.payment_identification,
        paymentDetailsObject.api_token,
        paymentDetailsObject
      );
    }
  } catch (error) {
    console.error("Error fetching order data:", error);
  }
};
const PixAiPayment = (props) => {
  const [qrCode, setQrCode] = useState(null);
  const [isPaid, setIsPaid] = useState(false);
  const { onPaymentProcessing, onPaymentSetup } = props.eventRegistration;

  const ajaxUrl = window.pixAiSettings?.storeApiUrl || "";

  const checkoutUrl = ajaxUrl + "/checkout";

  //   console.log("Submitting to WooCommerce Checkout API:", checkoutUrl);

  //   console.log("window.pixAiSettings: ", [window.pixAiSettings]);
  //   console.log("props: ", window.wc?.wcSettings);

  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      console.log("onPaymentSetup", [isPaid, isSubmitting]);

      if (isPaid || isSubmitting) return;

      isSubmitting = true;

      // Show loading message
      setQrCode("loading");

      if (props.orderId) {
        await fetchExistingOrder(props.orderId);
        isSubmitting = false;
        return;
      }

      const body = JSON.stringify({
        payment_method: props.activePaymentMethod,
        billing_address: props.billing.billingAddress,
        shipping_address: props.shippingData,
      });

      try {
        const response = await fetch(checkoutUrl, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WC-Store-API-Nonce": window.pixAiSettings.nonce,
            Nonce: window.pixAiSettings.nonce,
          },
          body: body,
        });

        let responseText;
        let data;

        try {
          responseText = await response.text();
        } catch (err) {
          console.log("err responseText: ", err);
        }

        try {
          data = JSON.parse(responseText);
        } catch (error) {
          console.log("error: ", error);
        }

        if (data) {
          if (data?.payment_result?.payment_status === "success") {
            const paymentDetailsObject = Object.fromEntries(
              data.payment_result.payment_details.map((detail) => [
                detail.key,
                detail.value,
              ])
            );

            console.log("paymentDetailsObject: ", paymentDetailsObject);

            setQrCode(paymentDetailsObject.qr_code); // Display the QR Code
            await waitForPaymentConfirmation(
              paymentDetailsObject.payment_identification,
              paymentDetailsObject.api_token,
              paymentDetailsObject
            );
          }
        }

        // if (data?.result === "success" && data?.qr_code) {
        //   setQrCode(data.qr_code); // Display the QR Code
        //   await waitForPaymentConfirmation(
        //     data.payment_identification,
        //     data.api_token
        //   );
        // } else {
        //   console.log("Erro ao gerar QR Code:", data);
        // }
      } catch (error) {
        console.log("error: ", error);

        console.log("Erro na requisição do QR Code:", JSON.stringify(error));
      } finally {
        isSubmitting = false;
      }
    });

    return () => unsubscribe();
  }, [ajaxUrl, onPaymentProcessing, isPaid]);

  // Function to check payment status every 5 seconds
  const waitForPaymentConfirmation = async (
    orderId,
    token,
    paymentDetailsObject
  ) => {
    const checkPaymentUrl = `https://manager.pixai.com.br/api/integration/payment-initiation/${orderId}`;

    // console.log("orderId, token: ", [orderId, token]);

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

        const orderId = paymentDetailsObject.order_id;

        const orderReceivedUrl = `/my-account/view-order/${orderId}`;

        // console.log("result: ", result);

        if (result.localPayment.status === "CONFIRMED") {
          clearInterval(interval);
          setIsPaid(true);

          //   fetch(`${window.pixAiSettings.storeApiUrl}/orders/${orderId}`, {
          fetch(`${window.pixAiSettings.checkApiUrl}/${orderId}`, {
            method: "GET",
          })
            .then((response) => response.json())
            .then((data) => {
              window.location.href = orderReceivedUrl;

              console.log("Order updated to paid:", data);
            })
            .catch((error) => console.error("Error updating order:", error));
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
      ? createElement("img", {
          src: `https://api.qrserver.com/v1/create-qr-code/?data=${encodeURIComponent(
            qrCode
          )}&size=200x200`,
          alt: "QR Code Pix Ai",
        })
      : null
  );
};

const PixAiPaymentContent = (props) => {
  //   console.log("Submitting to WooCommerce Checkout API:", checkoutUrl);

  //   console.log("window.pixAiSettings: ", [window.pixAiSettings]);
  //   console.log("props: ", window.wc?.wcSettings);

  // Function to check payment status every 5 seconds

  return createElement("div", null);
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
