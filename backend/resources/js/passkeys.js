import { Passkeys } from "@laravel/passkeys";

function message(root, value, error = false) {
  const node = root?.querySelector("[data-passkey-message]");
  if (!node) return;
  node.textContent = value || "";
  node.className = "notice" + (error ? " error" : "");
  node.hidden = !value;
}

const loginRoot = document.querySelector("[data-passkey-login-root]");
if (loginRoot) {
  const button = loginRoot.querySelector("[data-passkey-login]");
  if (button && Passkeys.isSupported()) {
    button.hidden = false;
    button.addEventListener("click", async () => {
      button.disabled = true;
      message(loginRoot, loginRoot.dataset.working || "…");
      try {
        const response = await Passkeys.verify();
        window.location.assign(response?.redirect || loginRoot.dataset.panel || "/fa/panel");
      } catch (error) {
        message(loginRoot, error?.message || loginRoot.dataset.error, true);
        button.disabled = false;
      }
    });
  }
}

const managementRoot = document.querySelector("[data-passkey-management]");
if (managementRoot) {
  const button = managementRoot.querySelector("[data-passkey-register]");
  const input = managementRoot.querySelector("[data-passkey-name]");

  if (button && Passkeys.isSupported()) {
    button.hidden = false;
    button.addEventListener("click", async () => {
      const name = input?.value?.trim();
      if (!name) {
        message(managementRoot, managementRoot.dataset.nameRequired, true);
        input?.focus();
        return;
      }

      button.disabled = true;
      message(managementRoot, managementRoot.dataset.working || "…");

      try {
        await Passkeys.register({ name });
        message(managementRoot, managementRoot.dataset.registered);
        window.location.reload();
      } catch (error) {
        message(managementRoot, error?.message || managementRoot.dataset.error, true);
        button.disabled = false;
      }
    });
  } else if (button) {
    message(managementRoot, managementRoot.dataset.unsupported, true);
  }
}
