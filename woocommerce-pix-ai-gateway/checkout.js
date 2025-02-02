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

function clearSessionData() {
  fetch("/wp-admin/admin-ajax.php?action=clear_pixai_payment_data", {
    method: "POST",
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        console.log("Session cleared:", data.message);
      } else {
        console.error("Failed to clear session:", data);
      }
    })
    .catch((error) => console.error("Error clearing session:", error));
}

async function fetchSectionData(setQrCode, setIsPaid) {
  return fetch("/wp-admin/admin-ajax.php?action=get_pixai_payment_data")
    .then((response) => response.json()) // Convert response to JSON
    .then(async (data) => {
      if (data.result === "success") {
        setQrCode(data.qr_code); // Display the QR Code

        await waitForPaymentConfirmation(
          data.payment_identification,
          data.api_token,
          data,
          setIsPaid
        );
      } else {
        console.error("Failed to retrieve payment data");
      }
    })
    .catch((error) => console.error("Error fetching payment data:", error));
}

const waitForPaymentConfirmation = async (
  orderId,
  token,
  paymentDetailsObject,
  setIsPaid
) => {
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

      const orderId = paymentDetailsObject.order_id;

      const orderReceivedUrl = `/my-account/view-order/${orderId}`;

      if (result.localPayment.status === "CONFIRMED") {
        clearInterval(interval);
        setIsPaid(true);

        fetch(`${window.pixAiSettings.checkApiUrl}/${orderId}`, {
          method: "GET",
        })
          .then((response) => response.json())
          .then((data) => {
            window.location.href = orderReceivedUrl;
            clearSessionData();
          })
          .catch((error) => console.error("Error updating order:", error));
      }
    } catch (error) {
      console.error("Erro ao verificar pagamento:", error);
    }
  }, 5000);
};

const PixAiPayment = (props) => {
  const [qrCode, setQrCode] = useState(null);
  const [isPaid, setIsPaid] = useState(false);
  const { onPaymentProcessing, onPaymentSetup } = props.eventRegistration;

  useEffect(() => {
    const unsubscribe = onPaymentSetup(async () => {
      if (isPaid || isSubmitting) return;

      isSubmitting = true;

      setTimeout(() => {
        fetchSectionData(setQrCode, setIsPaid);
      }, 2000);

      // Show loading message
      setQrCode("loading");
    });

    return () => unsubscribe();
  }, [onPaymentProcessing, isPaid]);

  // Function to check payment status every 5 seconds

  return createElement(
    "div",
    null,
    createElement("p", null, "Pressione pagar para gerar o QR Code."),
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
