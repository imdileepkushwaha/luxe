<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

seller_require_login(db());

header('Location: settings.php?notice=payment_gateways_admin', true, 302);
exit;
