<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$pdo = db();
$user = auth_user($pdo);
if (!$user) {
    header('Location: login.php?redirect=' . rawurlencode('address.php'));
    exit;
}

$userId = (int) ($user['id'] ?? 0);
$addresses = $userId > 0 ? addresses_fetch_for_user($pdo, $userId) : [];

$cartCount = 0;
foreach ($_SESSION['cart'] ?? [] as $item) {
    $cartCount += (int) ($item['qty'] ?? 1);
}

$fullName = trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')));
if ($fullName === '') {
    $fullName = 'Member';
}
$initial = strtoupper(substr((string) ($user['first_name'] ?? $fullName), 0, 1));
$isLoggedIn = true;
$userInitials = $initial;
$userName = $fullName;
$userEmail = trim((string) ($user['email'] ?? ''));
$theme1LoginHref = 'login.php?redirect=' . rawurlencode('address.php');

$theme1HeaderCategories = ["Men's Fashion", "Women's Fashion", "Kid's Fashion", 'Footwear'];
$theme1HeaderCompareCount = 0;
$theme1HeaderCartCount = $cartCount;
$theme1FooterCategories = $theme1HeaderCategories;

$memberSince = 'Recently joined';
$createdAt = (string) ($user['created_at'] ?? '');
if ($createdAt !== '' && strtotime($createdAt) !== false) {
    $memberSince = 'Member since ' . date('M Y', strtotime($createdAt));
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>LUXE Theme 1 - Addresses</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Inter:wght@400;500;600;700;800&family=Jost:wght@400;500;600;700&family=Outfit:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= h(luxe_theme_asset('css/styles.css')) ?>">
</head>
<body class="profile-page-wrap">
  <?php require __DIR__ . '/partials/header.php'; ?>

  <main>
    <section class="profile-shell" aria-label="Address content">
      <?php require __DIR__ . '/partials/profile-sidebar.php'; ?>

      <article class="profile-main">
        <div class="profile-main-head">
          <h2>Saved Addresses</h2>
          <button type="button" class="profile-edit-btn" id="addAddressBtn">+ Add New</button>
        </div>
        <div class="theme1-address-grid" id="addressesGrid"></div>
      </article>
    </section>
  </main>

  <div class="theme1-modal-overlay hidden" id="addressModal">
    <div class="theme1-modal-card">
      <div class="theme1-modal-header">
        <h3 id="addressModalTitle">Add address</h3>
        <button type="button" class="theme1-modal-close" id="addressModalClose" aria-label="Close">x</button>
      </div>
      <form id="addressForm">
        <input type="hidden" id="addressId" value="" />
        <div class="theme1-form-grid">
          <div class="theme1-form-field"><label for="addrName">Full Name</label><input type="text" id="addrName" required maxlength="255" autocomplete="name"></div>
          <div class="theme1-form-field"><label for="addrPhone">Phone</label><input type="tel" id="addrPhone" maxlength="40" autocomplete="tel"></div>
          <div class="theme1-form-field theme1-col-span-2"><label for="addrLine1">Address Line 1</label><input type="text" id="addrLine1" required maxlength="255" autocomplete="address-line1"></div>
          <div class="theme1-form-field theme1-col-span-2"><label for="addrLine2">Address Line 2</label><input type="text" id="addrLine2" maxlength="255" autocomplete="address-line2"></div>
          <div class="theme1-form-field"><label for="addrCity">City</label><input type="text" id="addrCity" required maxlength="100" autocomplete="address-level2"></div>
          <div class="theme1-form-field"><label for="addrPin">PIN Code</label><input type="text" id="addrPin" required maxlength="20" autocomplete="postal-code"></div>
          <div class="theme1-form-field"><label for="addrState">State</label><input type="text" id="addrState" required maxlength="100" autocomplete="address-level1"></div>
          <div class="theme1-form-field">
            <label for="addrType">Type</label>
            <select id="addrType">
              <option value="Home">Home</option>
              <option value="Work">Work</option>
              <option value="Other">Other</option>
            </select>
          </div>
          <div class="theme1-form-field theme1-col-span-2">
            <label class="theme1-checkbox-label"><input type="checkbox" id="addrIsDefault"> <span>Set as default address</span></label>
          </div>
        </div>
        <div class="theme1-form-actions">
          <button type="submit" class="profile-edit-btn" id="addressSaveBtn">Save address</button>
          <button type="button" class="profile-edit-cancel" id="addressCancelBtn">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <?php require __DIR__ . '/partials/footer.php'; ?>

  <script>
    (function () {
      const LUXE_ACT = <?= json_encode(luxe_actions_root_url(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>;
      var addresses = <?= json_encode($addresses, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) ?>;
      var grid = document.getElementById("addressesGrid");
      var modal = document.getElementById("addressModal");
      var form = document.getElementById("addressForm");

      function esc(s) {
        return String(s || "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
      }

      function openModal(editItem) {
        document.getElementById("addressModalTitle").textContent = editItem ? "Edit address" : "Add address";
        form.reset();
        document.getElementById("addressId").value = editItem ? String(editItem.id || "") : "";
        document.getElementById("addrName").value = editItem ? (editItem.name || "") : "";
        document.getElementById("addrPhone").value = editItem ? (editItem.phone || "") : "";
        document.getElementById("addrLine1").value = editItem ? (editItem.line1 || "") : "";
        document.getElementById("addrLine2").value = editItem ? (editItem.line2 || "") : "";
        document.getElementById("addrCity").value = editItem ? (editItem.city || "") : "";
        document.getElementById("addrPin").value = editItem ? (editItem.pin || "") : "";
        document.getElementById("addrState").value = editItem ? (editItem.state || "") : "";
        document.getElementById("addrType").value = editItem ? (editItem.type || "Home") : "Home";
        document.getElementById("addrIsDefault").checked = editItem ? !!editItem.isDefault : addresses.length === 0;
        modal.classList.remove("hidden");
      }

      function closeModal() {
        modal.classList.add("hidden");
      }

      function render() {
        if (!grid) return;
        if (!addresses.length) {
          grid.innerHTML = '<div class="theme1-address-empty">No address saved yet. Click "+ Add New" to add your first address.</div>';
          return;
        }
        grid.innerHTML = addresses.map(function (a) {
          var line2 = (a.line2 || "").trim() ? ", " + esc(a.line2) : "";
          return '<div class="theme1-address-card">' +
            '<div class="theme1-address-top"><span class="theme1-address-type">' + esc(a.type || "Home") + '</span>' + (a.isDefault ? '<span class="theme1-address-default">Default</span>' : '') + '</div>' +
            '<strong>' + esc(a.name || "") + '</strong>' +
            '<p>' + esc(a.line1 || "") + line2 + ', ' + esc(a.city || "") + ', ' + esc(a.state || "") + ' - ' + esc(a.pin || "") + '</p>' +
            '<p>' + (a.phone ? ('Phone: ' + esc(a.phone)) : '') + '</p>' +
            '<div class="theme1-address-actions">' +
            '<button type="button" data-edit-id="' + Number(a.id || 0) + '">Edit</button>' +
            '</div>' +
            '</div>';
        }).join("");
        grid.querySelectorAll("[data-edit-id]").forEach(function (btn) {
          btn.addEventListener("click", function () {
            var id = Number(btn.getAttribute("data-edit-id"));
            var current = addresses.find(function (x) { return Number(x.id) === id; });
            if (current) openModal(current);
          });
        });
      }

      document.getElementById("addAddressBtn").addEventListener("click", function () {
        openModal(null);
      });
      document.getElementById("addressModalClose").addEventListener("click", closeModal);
      document.getElementById("addressCancelBtn").addEventListener("click", closeModal);
      modal.addEventListener("click", function (e) {
        if (e.target === modal) closeModal();
      });

      form.addEventListener("submit", async function (e) {
        e.preventDefault();
        var saveBtn = document.getElementById("addressSaveBtn");
        var idVal = parseInt(document.getElementById("addressId").value || "0", 10);
        var payload = {
          id: idVal > 0 ? idVal : undefined,
          type: document.getElementById("addrType").value || "Home",
          name: document.getElementById("addrName").value.trim(),
          phone: document.getElementById("addrPhone").value.trim(),
          line1: document.getElementById("addrLine1").value.trim(),
          line2: document.getElementById("addrLine2").value.trim(),
          city: document.getElementById("addrCity").value.trim(),
          state: document.getElementById("addrState").value.trim(),
          pin: document.getElementById("addrPin").value.trim(),
          is_default: !!document.getElementById("addrIsDefault").checked
        };
        saveBtn.disabled = true;
        try {
          var res = await fetch(LUXE_ACT + "save-address.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            credentials: "same-origin",
            body: JSON.stringify(payload)
          });
          var data = await res.json();
          if (!res.ok || !data.ok) {
            alert(data.message || "Could not save address.");
            return;
          }
          addresses = Array.isArray(data.addresses) ? data.addresses : addresses;
          closeModal();
          render();
        } catch (_e) {
          alert("Network error. Please try again.");
        } finally {
          saveBtn.disabled = false;
        }
      });

      render();
    })();
  </script>
</body>
</html>
