<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/admin_header.php';

$isSuper = isSuperAdmin();

// ============================================================
// EMPLOYEE DASHBOARD
// Employees only ever see the income from orders THEY personally
// handled/delivered — never the store's total revenue or the
// unpaid "Holding Income" figure. Those numbers are Super Admin only.
// ============================================================
if (!$isSuper) {
    $empId = (int)$_SESSION['employee_id'];

    $recvStmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE handled_by = ?");
    $recvStmt->execute([$empId]);
    $myReceivedIncome = (float)$recvStmt->fetchColumn();

    $handledStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE handled_by = ?");
    $handledStmt->execute([$empId]);
    $myOrdersHandled = (int)$handledStmt->fetchColumn();

    $prodStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE created_by = ?");
    $prodStmt->execute([$empId]);
    $myProductsAdded = (int)$prodStmt->fetchColumn();

    $reviewStmt = $pdo->prepare("SELECT COUNT(*) FROM reviews WHERE reviewed_by = ?");
    $reviewStmt->execute([$empId]);
    $myReviewsModerated = (int)$reviewStmt->fetchColumn();

    $allStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    $statusColors = [
        'pending' => '#D97706', 'processing' => '#2563EB', 'shipped' => '#4338CA',
        'delivered' => '#059669', 'cancelled' => '#DC2626',
    ];
    $myStatusStmt = $pdo->prepare("SELECT order_status, COUNT(*) cnt FROM orders WHERE handled_by = ? GROUP BY order_status");
    $myStatusStmt->execute([$empId]);
    $myStatusCounts = $myStatusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $myStatusData = [];
    foreach ($allStatuses as $s) { $myStatusData[$s] = (int)($myStatusCounts[$s] ?? 0); }

    $myRecentStmt = $pdo->prepare("SELECT * FROM orders WHERE handled_by = ? ORDER BY updated_at DESC LIMIT 8");
    $myRecentStmt->execute([$empId]);
    $myRecentOrders = $myRecentStmt->fetchAll();
    ?>

    <div class="stat-grid">
        <div class="stat-card">
            <div style="display:flex;align-items:center;gap:14px;width:100%;">
                <div class="icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div><div class="value"><?= formatPrice($myReceivedIncome) ?></div><div class="label">My Received Income</div></div>
            </div>
        </div>
        <div class="stat-card">
            <div style="display:flex;align-items:center;gap:14px;width:100%;">
                <div class="icon" style="background:var(--a-info-bg);color:var(--a-info);"><i class="fa-solid fa-cart-shopping"></i></div>
                <div><div class="value"><?= $myOrdersHandled ?></div><div class="label">Orders Handled</div></div>
            </div>
        </div>
        <div class="stat-card">
            <div style="display:flex;align-items:center;gap:14px;width:100%;">
                <div class="icon" style="background:var(--a-warning-bg);color:var(--a-warning);"><i class="fa-solid fa-shirt"></i></div>
                <div><div class="value"><?= $myProductsAdded ?></div><div class="label">Products Added</div></div>
            </div>
        </div>
        <div class="stat-card">
            <div style="display:flex;align-items:center;gap:14px;width:100%;">
                <div class="icon" style="background:var(--a-success-bg,rgba(5,150,105,.12));color:var(--a-success,#059669);"><i class="fa-solid fa-star-half-stroke"></i></div>
                <div><div class="value"><?= $myReviewsModerated ?></div><div class="label">Reviews Moderated</div></div>
            </div>
        </div>
    </div>

    <div class="chart-grid">
        <div class="chart-card">
            <div class="chart-header"><h3>My Orders by Status</h3></div>
            <div class="chart-sub">All orders you have handled</div>
            <?php if (array_sum($myStatusData) === 0): ?>
                <div class="chart-canvas-wrap" style="height:220px;display:flex;align-items:center;justify-content:center;">
                    <p style="color:var(--a-text-light);font-size:13.5px;">You haven't handled any orders yet.</p>
                </div>
            <?php else: ?>
                <div class="chart-canvas-wrap" style="height:220px;"><canvas id="myOrderStatusChart"></canvas></div>
            <?php endif; ?>
            <ul class="chart-legend-list">
                <?php foreach ($allStatuses as $s): ?>
                <li>
                    <span class="label"><span class="dot" style="background:<?= $statusColors[$s] ?>;"></span><?= ucfirst($s) ?></span>
                    <span class="val"><?= (int)$myStatusData[$s] ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="panel" style="margin-bottom:0;">
            <div class="panel-header"><h3>Recently Handled Orders</h3></div>
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php if (empty($myRecentOrders)): ?>
                        <tr><td colspan="4" style="text-align:center;color:var(--a-text-light);">No orders yet</td></tr>
                    <?php endif; ?>
                    <?php foreach ($myRecentOrders as $o): ?>
                        <tr>
                            <td><a href="order-view.php?id=<?= (int)$o['id'] ?>"><?= sanitize($o['order_number']) ?></a></td>
                            <td><?= sanitize($o['guest_name']) ?></td>
                            <td><?= formatPrice($o['total_amount']) ?></td>
                            <td><span class="status-badge status-<?= sanitize($o['order_status']) ?>"><?= sanitize($o['order_status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="../assets/js/vendor/chartjs/chart.umd.js"></script>
    <script>
    (function () {
        if (typeof Chart === 'undefined') return;
        var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        Chart.defaults.font.family = "'Inter', sans-serif";
        Chart.defaults.color = isDark ? '#A2A6B5' : '#6B7280';
        var canvas = document.getElementById('myOrderStatusChart');
        if (canvas) {
            new Chart(canvas, {
                type: 'doughnut',
                data: {
                    labels: <?= json_encode(array_map('ucfirst', $allStatuses)) ?>,
                    datasets: [{
                        data: <?= json_encode(array_values($myStatusData)) ?>,
                        backgroundColor: <?= json_encode(array_values($statusColors)) ?>,
                        borderWidth: 0,
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '68%',
                    plugins: { legend: { display: false } }
                }
            });
        }
    })();
    </script>

    <?php
    require_once __DIR__ . '/includes/admin_footer.php';
    exit;
}

// ============================================================
// SUPER ADMIN DASHBOARD (full store view)
// ============================================================

// ------------------------------------------------------------
// Date range resolution based on the selected filter
// ------------------------------------------------------------
$range = $_GET['range'] ?? 'this_month';
$validRanges = ['this_month', 'last_month', 'last_3_months', 'last_6_months', 'all_time'];
if (!in_array($range, $validRanges)) $range = 'this_month';

$now = new DateTime();
$bucketBy = 'day'; // 'day' or 'month' for the trend chart

switch ($range) {
    case 'last_month':
        $start = new DateTime('first day of last month midnight');
        $end = new DateTime('last day of last month 23:59:59');
        $prevStart = new DateTime('first day of -2 months midnight');
        $prevEnd = new DateTime('last day of -2 months 23:59:59');
        $rangeLabel = 'Last Month';
        break;
    case 'last_3_months':
        $start = (new DateTime('-3 months'))->setTime(0, 0, 0);
        $end = $now;
        $prevStart = (new DateTime('-6 months'))->setTime(0, 0, 0);
        $prevEnd = (new DateTime('-3 months'))->setTime(23, 59, 59);
        $rangeLabel = 'Last 3 Months';
        $bucketBy = 'month';
        break;
    case 'last_6_months':
        $start = (new DateTime('-6 months'))->setTime(0, 0, 0);
        $end = $now;
        $prevStart = (new DateTime('-12 months'))->setTime(0, 0, 0);
        $prevEnd = (new DateTime('-6 months'))->setTime(23, 59, 59);
        $rangeLabel = 'Last 6 Months';
        $bucketBy = 'month';
        break;
    case 'all_time':
        $start = new DateTime('2000-01-01');
        $end = $now;
        $prevStart = null;
        $prevEnd = null;
        $rangeLabel = 'All Time';
        $bucketBy = 'month';
        break;
    default: // this_month
        $start = new DateTime('first day of this month midnight');
        $end = $now;
        $prevStart = new DateTime('first day of last month midnight');
        $prevEnd = new DateTime('last day of last month 23:59:59');
        $rangeLabel = 'This Month';
}

$startStr = $start->format('Y-m-d H:i:s');
$endStr = $end->format('Y-m-d H:i:s');

// ------------------------------------------------------------
// Helper to pull revenue / holding income / orders / new customers for a period
// Revenue = only orders that are actually PAID (money in hand).
// Holding Income = orders placed but NOT yet paid (money not yet realized),
// excluding cancelled orders since those will never be collected.
// ------------------------------------------------------------
function periodStats(PDO $pdo, $startStr, $endStr) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE created_at BETWEEN ? AND ? AND (payment_status='paid' OR order_status='delivered')");
    $stmt->execute([$startStr, $endStr]);
    $revenue = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE created_at BETWEEN ? AND ? AND payment_status='unpaid' AND order_status != 'cancelled'");
    $stmt->execute([$startStr, $endStr]);
    $holdingIncome = (float)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$startStr, $endStr]);
    $orders = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE created_at BETWEEN ? AND ?");
    $stmt->execute([$startStr, $endStr]);
    $customers = (int)$stmt->fetchColumn();

    return compact('revenue', 'holdingIncome', 'orders', 'customers');
}

function pctChange($current, $previous) {
    if ($previous == 0) return $current > 0 ? ['dir' => 'up', 'pct' => 100] : ['dir' => 'flat', 'pct' => 0];
    $pct = round((($current - $previous) / $previous) * 100, 1);
    if (abs($pct) < 0.1) return ['dir' => 'flat', 'pct' => 0];
    return ['dir' => $pct > 0 ? 'up' : 'down', 'pct' => abs($pct)];
}

$current = periodStats($pdo, $startStr, $endStr);
$previous = ($prevStart && $prevEnd) ? periodStats($pdo, $prevStart->format('Y-m-d H:i:s'), $prevEnd->format('Y-m-d H:i:s')) : null;

$trendRevenue = $previous ? pctChange($current['revenue'], $previous['revenue']) : null;
$trendHolding = $previous ? pctChange($current['holdingIncome'], $previous['holdingIncome']) : null;
$trendOrders = $previous ? pctChange($current['orders'], $previous['orders']) : null;
$trendCustomers = $previous ? pctChange($current['customers'], $previous['customers']) : null;

// ------------------------------------------------------------
// Profit = Sale Amount - Purchase Amount (cost_price), for orders
// that are actually paid/delivered (money in hand), within range.
// Line items with no recorded purchase amount are treated as 0 cost.
// ------------------------------------------------------------
function periodProfit(PDO $pdo, $startStr, $endStr) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(oi.line_total),0) AS sale_amount,
            COALESCE(SUM(COALESCE(p.cost_price,0) * oi.quantity),0) AS purchase_amount
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE o.created_at BETWEEN ? AND ? AND (o.payment_status='paid' OR o.order_status='delivered')");
    $stmt->execute([$startStr, $endStr]);
    $row = $stmt->fetch();
    $saleAmount = (float)$row['sale_amount'];
    $purchaseAmount = (float)$row['purchase_amount'];
    return ['saleAmount' => $saleAmount, 'purchaseAmount' => $purchaseAmount, 'profit' => $saleAmount - $purchaseAmount];
}

$currentProfit = periodProfit($pdo, $startStr, $endStr);
$previousProfit = ($prevStart && $prevEnd) ? periodProfit($pdo, $prevStart->format('Y-m-d H:i:s'), $prevEnd->format('Y-m-d H:i:s')) : null;
$trendProfit = $previousProfit ? pctChange($currentProfit['profit'], $previousProfit['profit']) : null;

// ------------------------------------------------------------
// Revenue vs Holding Income trend chart (bucketed by day or month)
// ------------------------------------------------------------
$chartLabels = [];
$chartRevenue = [];
$chartHolding = [];

if ($bucketBy === 'day') {
    $revStmt = $pdo->prepare("SELECT DATE(created_at) d, COALESCE(SUM(total_amount),0) v FROM orders
        WHERE created_at BETWEEN ? AND ? AND (payment_status='paid' OR order_status='delivered') GROUP BY DATE(created_at)");
    $revStmt->execute([$startStr, $endStr]);
    $revByDay = $revStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $holdStmt = $pdo->prepare("SELECT DATE(created_at) d, COALESCE(SUM(total_amount),0) v FROM orders
        WHERE created_at BETWEEN ? AND ? AND payment_status='unpaid' AND order_status != 'cancelled' GROUP BY DATE(created_at)");
    $holdStmt->execute([$startStr, $endStr]);
    $holdByDay = $holdStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $cursor = clone $start;
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m-d');
        $chartLabels[] = $cursor->format('d M');
        $chartRevenue[] = round((float)($revByDay[$key] ?? 0), 2);
        $chartHolding[] = round((float)($holdByDay[$key] ?? 0), 2);
        $cursor->modify('+1 day');
    }
} else {
    $revStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COALESCE(SUM(total_amount),0) v FROM orders
        WHERE created_at BETWEEN ? AND ? AND (payment_status='paid' OR order_status='delivered') GROUP BY ym");
    $revStmt->execute([$startStr, $endStr]);
    $revByMonth = $revStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $holdStmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COALESCE(SUM(total_amount),0) v FROM orders
        WHERE created_at BETWEEN ? AND ? AND payment_status='unpaid' AND order_status != 'cancelled' GROUP BY ym");
    $holdStmt->execute([$startStr, $endStr]);
    $holdByMonth = $holdStmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $cursor = clone $start;
    $cursor->modify('first day of this month');
    $endMonth = clone $end;
    $endMonth->modify('first day of this month');
    while ($cursor <= $endMonth) {
        $key = $cursor->format('Y-m');
        $chartLabels[] = $cursor->format('M Y');
        $chartRevenue[] = round((float)($revByMonth[$key] ?? 0), 2);
        $chartHolding[] = round((float)($holdByMonth[$key] ?? 0), 2);
        $cursor->modify('+1 month');
    }
}

// ------------------------------------------------------------
// Order status pie chart (within selected range)
// ------------------------------------------------------------
$statusStmt = $pdo->prepare("SELECT order_status, COUNT(*) cnt FROM orders WHERE created_at BETWEEN ? AND ? GROUP BY order_status");
$statusStmt->execute([$startStr, $endStr]);
$statusCounts = $statusStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$allStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
$statusData = [];
foreach ($allStatuses as $s) { $statusData[$s] = (int)($statusCounts[$s] ?? 0); }
$statusColors = [
    'pending' => '#D97706', 'processing' => '#2563EB', 'shipped' => '#4338CA',
    'delivered' => '#059669', 'cancelled' => '#DC2626',
];

// ------------------------------------------------------------
// Customer growth bar chart — fixed to the last 6 months, independent of filter
// ------------------------------------------------------------
$custStmt = $pdo->query("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) cnt FROM customers
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) GROUP BY ym");
$custByMonth = $custStmt->fetchAll(PDO::FETCH_KEY_PAIR);
$custLabels = [];
$custData = [];
$cCursor = new DateTime('-5 months');
$cCursor->modify('first day of this month');
for ($i = 0; $i < 6; $i++) {
    $key = $cCursor->format('Y-m');
    $custLabels[] = $cCursor->format('M');
    $custData[] = (int)($custByMonth[$key] ?? 0);
    $cCursor->modify('+1 month');
}

// ------------------------------------------------------------
// Order Value Distribution (dot / scatter chart) — every order in range
// ------------------------------------------------------------
$ovStmt = $pdo->prepare("SELECT created_at, total_amount, order_status FROM orders WHERE created_at BETWEEN ? AND ? ORDER BY created_at ASC");
$ovStmt->execute([$startStr, $endStr]);
$orderRows = $ovStmt->fetchAll();
$orderPoints = [];
$orderPointColors = [];
$orderPointDates = [];
$oi = 0;
foreach ($orderRows as $row) {
    $oi++;
    $orderPoints[] = ['x' => $oi, 'y' => (float)$row['total_amount']];
    $orderPointColors[] = $statusColors[$row['order_status']] ?? '#6B7280';
    $orderPointDates[] = date('d M Y', strtotime($row['created_at']));
}

// ------------------------------------------------------------
// Top Selling Products (bar chart) — within selected range
// ------------------------------------------------------------
$topStmt = $pdo->prepare("SELECT oi.product_name, SUM(oi.quantity) qty
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    WHERE o.created_at BETWEEN ? AND ? AND (o.payment_status='paid' OR o.order_status='delivered')
    GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 5");
$topStmt->execute([$startStr, $endStr]);
$topProducts = $topStmt->fetchAll();
$topProductLabels = array_map(fn($r) => strlen($r['product_name']) > 22 ? substr($r['product_name'], 0, 20) . '…' : $r['product_name'], $topProducts);
$topProductQty = array_map(fn($r) => (int)$r['qty'], $topProducts);

// ------------------------------------------------------------
// Sales by Category (pie chart) — within selected range
// ------------------------------------------------------------
$catStmt = $pdo->prepare("SELECT c.name, SUM(oi.line_total) rev
    FROM order_items oi JOIN orders o ON o.id = oi.order_id
    JOIN products p ON p.id = oi.product_id JOIN categories c ON c.id = p.category_id
    WHERE o.created_at BETWEEN ? AND ? AND (o.payment_status='paid' OR o.order_status='delivered')
    GROUP BY c.id, c.name ORDER BY rev DESC");
$catStmt->execute([$startStr, $endStr]);
$catRows = $catStmt->fetchAll();
$catPalette = ['#4F46E5', '#059669', '#D97706', '#DC2626', '#2563EB', '#DB2777', '#0891B2', '#7C3AED'];
$catLabels = array_map(fn($r) => $r['name'], $catRows);
$catRevenue = array_map(fn($r) => round((float)$r['rev'], 2), $catRows);
$catColors = [];
foreach ($catRows as $idx => $r) { $catColors[] = $catPalette[$idx % count($catPalette)]; }

// ------------------------------------------------------------
// Existing widgets
// ------------------------------------------------------------
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$totalRevenue = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status='paid' OR order_status='delivered'")->fetchColumn();
$pendingOrders = $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status='pending'")->fetchColumn();
$lowStock = $pdo->query("SELECT * FROM products WHERE stock <= 10 ORDER BY stock ASC LIMIT 5")->fetchAll();
$recentOrders = $pdo->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 8")->fetchAll();

if (isset($_GET['error']) && $_GET['error'] === 'access_denied') {
    echo '<div class="alert alert-error">You do not have permission to access that page. Super Admin only.</div>';
}

function trendBadge($trend) {
    if (!$trend) return '';
    $icon = $trend['dir'] === 'up' ? 'fa-arrow-up' : ($trend['dir'] === 'down' ? 'fa-arrow-down' : 'fa-minus');
    return "<span class=\"trend {$trend['dir']}\"><i class=\"fa-solid $icon\"></i> {$trend['pct']}%</span>";
}
?>

<div class="dash-filter-bar">
    <span style="font-size:13px;color:var(--a-text-light);">Showing data for <strong style="color:var(--a-text);"><?= sanitize($rangeLabel) ?></strong><?= $previous ? ' · compared to the previous period' : '' ?></span>
    <div class="filter-tabs">
        <a href="dashboard.php?range=this_month" class="<?= $range === 'this_month' ? 'active' : '' ?>">This Month</a>
        <a href="dashboard.php?range=last_month" class="<?= $range === 'last_month' ? 'active' : '' ?>">Last Month</a>
        <a href="dashboard.php?range=last_3_months" class="<?= $range === 'last_3_months' ? 'active' : '' ?>">3 Months</a>
        <a href="dashboard.php?range=last_6_months" class="<?= $range === 'last_6_months' ? 'active' : '' ?>">6 Months</a>
        <a href="dashboard.php?range=all_time" class="<?= $range === 'all_time' ? 'active' : '' ?>">All Time</a>
    </div>
</div>

<div class="stat-grid">
    <div class="stat-card with-trend">
        <div style="display:flex;align-items:center;gap:14px;width:100%;">
            <div class="icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div><div class="value"><?= formatPrice($current['revenue']) ?></div><div class="label">Revenue</div></div>
        </div>
        <?= trendBadge($trendRevenue) ?>
    </div>
    <div class="stat-card with-trend">
        <div style="display:flex;align-items:center;gap:14px;width:100%;">
            <div class="icon" style="background:var(--a-warning-bg);color:var(--a-warning);"><i class="fa-solid fa-hourglass-half"></i></div>
            <div><div class="value"><?= formatPrice($current['holdingIncome']) ?></div><div class="label">Holding Income</div></div>
        </div>
        <?= trendBadge($trendHolding) ?>
    </div>
    <div class="stat-card with-trend">
        <div style="display:flex;align-items:center;gap:14px;width:100%;">
            <div class="icon" style="background:rgba(5,150,105,.12);color:#059669;"><i class="fa-solid fa-coins"></i></div>
            <div><div class="value"><?= formatPrice($currentProfit['profit']) ?></div><div class="label">Profit</div></div>
        </div>
        <?= trendBadge($trendProfit) ?>
    </div>
    <div class="stat-card with-trend">
        <div style="display:flex;align-items:center;gap:14px;width:100%;">
            <div class="icon" style="background:var(--a-info-bg);color:var(--a-info);"><i class="fa-solid fa-cart-shopping"></i></div>
            <div><div class="value"><?= (int)$current['orders'] ?></div><div class="label">Orders</div></div>
        </div>
        <?= trendBadge($trendOrders) ?>
    </div>
    <div class="stat-card with-trend">
        <div style="display:flex;align-items:center;gap:14px;width:100%;">
            <div class="icon" style="background:var(--a-warning-bg);color:var(--a-warning);"><i class="fa-solid fa-user-plus"></i></div>
            <div><div class="value"><?= (int)$current['customers'] ?></div><div class="label">New Customers</div></div>
        </div>
        <?= trendBadge($trendCustomers) ?>
    </div>
</div>

<div class="chart-grid">
    <div class="chart-card">
        <div class="chart-header">
            <h3>Revenue vs Holding Income</h3>
        </div>
        <div class="chart-sub"><?= sanitize($rangeLabel) ?> · <?= $bucketBy === 'day' ? 'daily' : 'monthly' ?> breakdown</div>
        <div class="chart-canvas-wrap"><canvas id="revenueProfitChart"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="chart-header"><h3>Orders by Status</h3></div>
        <div class="chart-sub"><?= sanitize($rangeLabel) ?></div>
        <?php if (array_sum($statusData) === 0): ?>
            <div class="chart-canvas-wrap" style="height:200px;display:flex;align-items:center;justify-content:center;">
                <p style="color:var(--a-text-light);font-size:13.5px;">No orders in this period yet.</p>
            </div>
        <?php else: ?>
            <div class="chart-canvas-wrap" style="height:200px;"><canvas id="orderStatusChart"></canvas></div>
        <?php endif; ?>
        <ul class="chart-legend-list">
            <?php foreach ($allStatuses as $s): ?>
            <li>
                <span class="label"><span class="dot" style="background:<?= $statusColors[$s] ?>;"></span><?= ucfirst($s) ?></span>
                <span class="val"><?= (int)$statusData[$s] ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>

<div class="chart-grid-2">
    <div class="chart-card">
        <div class="chart-header"><h3>New Customers</h3></div>
        <div class="chart-sub">Last 6 months</div>
        <div class="chart-canvas-wrap" style="height:200px;"><canvas id="customerGrowthChart"></canvas></div>
    </div>

    <div class="panel" style="margin-bottom:0;">
        <div class="panel-header"><h3>Alerts</h3></div>
        <p style="font-size:13.5px;margin-bottom:14px;"><i class="fa-solid fa-triangle-exclamation" style="color:var(--a-warning);"></i> <?= (int)$pendingOrders ?> orders pending processing</p>
        <h4 style="font-size:13.5px;margin-bottom:10px;">Low Stock Products</h4>
        <?php if (empty($lowStock)): ?>
            <p style="color:var(--a-text-light);font-size:13.5px;">All products are well stocked.</p>
        <?php endif; ?>
        <?php foreach ($lowStock as $p): ?>
        <div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--a-border);font-size:13.5px;">
            <span><?= sanitize($p['name']) ?></span>
            <span style="color:var(--a-danger);font-weight:600;"><?= (int)$p['stock'] ?> left</span>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="chart-grid-3">
    <div class="chart-card">
        <div class="chart-header"><h3>Order Value Distribution</h3></div>
        <div class="chart-sub"><?= sanitize($rangeLabel) ?> · each dot is one order</div>
        <div class="chart-canvas-wrap" style="height:220px;"><canvas id="orderValueDotChart"></canvas></div>
    </div>
    <div class="chart-card">
        <div class="chart-header"><h3>Top Selling Products</h3></div>
        <div class="chart-sub"><?= sanitize($rangeLabel) ?> · by units sold</div>
        <div class="chart-canvas-wrap" style="height:220px;">
            <?php if (empty($topProducts)): ?>
                <p style="color:var(--a-text-light);font-size:13.5px;">No sales in this period yet.</p>
            <?php else: ?>
                <canvas id="topProductsChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <div class="chart-card">
        <div class="chart-header"><h3>Sales by Category</h3></div>
        <div class="chart-sub"><?= sanitize($rangeLabel) ?> · share of revenue</div>
        <div class="chart-canvas-wrap" style="height:220px;">
            <?php if (empty($catRows)): ?>
                <p style="color:var(--a-text-light);font-size:13.5px;">No sales in this period yet.</p>
            <?php else: ?>
                <canvas id="categoryPieChart"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header">
        <h3>Recent Orders</h3>
        <a href="orders.php" class="btn btn-sm btn-outline">View All</a>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php if (empty($recentOrders)): ?>
                <tr><td colspan="5" style="text-align:center;color:var(--a-text-light);">No orders yet</td></tr>
            <?php endif; ?>
            <?php foreach ($recentOrders as $o): ?>
                <tr>
                    <td><a href="order-view.php?id=<?= (int)$o['id'] ?>"><?= sanitize($o['order_number']) ?></a></td>
                    <td><?= sanitize($o['guest_name']) ?></td>
                    <td><?= formatPrice($o['total_amount']) ?></td>
                    <td><span class="status-badge status-<?= sanitize($o['order_status']) ?>"><?= sanitize($o['order_status']) ?></span></td>
                    <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="../assets/js/vendor/chartjs/chart.umd.js"></script>
<script>
(function () {
    // If Chart.js failed to load for any reason, stop here instead of throwing
    // and silently leaving every canvas on the page blank.
    if (typeof Chart === 'undefined') {
        console.error('Chart.js did not load — dashboard charts cannot render.');
        return;
    }
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(17,24,39,.06)';
    var tickColor = isDark ? '#A2A6B5' : '#6B7280';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = tickColor;

    // Track every chart instance so the colors can be refreshed instantly
    // when the admin flips light/dark mode, instead of needing a reload.
    var dashCharts = [];

    var revenueHoldingCanvas = document.getElementById('revenueProfitChart');
    var revenueHoldingChart = revenueHoldingCanvas ? new Chart(revenueHoldingCanvas, {
        type: 'line',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Revenue',
                    data: <?= json_encode($chartRevenue) ?>,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79,70,229,.12)',
                    fill: true, tension: 0.35, pointRadius: 2, borderWidth: 2.5
                },
                {
                    label: 'Holding Income',
                    data: <?= json_encode($chartHolding) ?>,
                    borderColor: '#D97706',
                    backgroundColor: 'rgba(217,119,6,.12)',
                    fill: true, tension: 0.35, pointRadius: 2, borderWidth: 2.5
                }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } },
            scales: {
                x: { grid: { display: false } },
                y: { grid: { color: gridColor }, ticks: { callback: function (v) { return '<?= sanitize(setting("currency_symbol","৳")) ?>' + v; } } }
            }
        }
    }) : null;

    if (revenueHoldingChart) dashCharts.push(revenueHoldingChart);

    var orderStatusCanvas = document.getElementById('orderStatusChart');
    if (orderStatusCanvas) {
        dashCharts.push(new Chart(orderStatusCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map('ucfirst', $allStatuses)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($statusData)) ?>,
                    backgroundColor: <?= json_encode(array_values($statusColors)) ?>,
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: { legend: { display: false } }
            }
        }));
    }

    var customerGrowthCanvas = document.getElementById('customerGrowthChart');
    if (customerGrowthCanvas) {
        dashCharts.push(new Chart(customerGrowthCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($custLabels) ?>,
                datasets: [{
                    label: 'New Customers',
                    data: <?= json_encode($custData) ?>,
                    backgroundColor: '#818CF8',
                    borderRadius: 6, maxBarThickness: 36,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: gridColor }, ticks: { stepSize: 1, precision: 0 } }
                }
            }
        }));
    }

    // Order Value Distribution — dot / scatter chart
    var ovCanvas = document.getElementById('orderValueDotChart');
    if (ovCanvas) {
        var orderDates = <?= json_encode($orderPointDates) ?>;
        dashCharts.push(new Chart(ovCanvas, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Order Total',
                    data: <?= json_encode($orderPoints) ?>,
                    pointBackgroundColor: <?= json_encode($orderPointColors) ?>,
                    pointBorderColor: <?= json_encode($orderPointColors) ?>,
                    pointRadius: 5,
                    pointHoverRadius: 7,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function (items) {
                                var i = items[0].dataIndex;
                                return orderDates[i] || '';
                            },
                            label: function (item) {
                                return '<?= sanitize(setting("currency_symbol","৳")) ?>' + item.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    x: { title: { display: true, text: 'Order sequence', font: { size: 11 } }, grid: { color: gridColor }, ticks: { display: false } },
                    y: { grid: { color: gridColor }, ticks: { callback: function (v) { return '<?= sanitize(setting("currency_symbol","৳")) ?>' + v; } } }
                }
            }
        }));
    }

    // Top Selling Products — horizontal bar chart
    var topCanvas = document.getElementById('topProductsChart');
    if (topCanvas) {
        dashCharts.push(new Chart(topCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($topProductLabels) ?>,
                datasets: [{
                    label: 'Units Sold',
                    data: <?= json_encode($topProductQty) ?>,
                    backgroundColor: '#4F46E5',
                    borderRadius: 6, maxBarThickness: 22,
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { stepSize: 1, precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        }));
    }

    // Sales by Category — pie chart
    var catCanvas = document.getElementById('categoryPieChart');
    if (catCanvas) {
        dashCharts.push(new Chart(catCanvas, {
            type: 'pie',
            data: {
                labels: <?= json_encode($catLabels) ?>,
                datasets: [{
                    data: <?= json_encode($catRevenue) ?>,
                    backgroundColor: <?= json_encode($catColors) ?>,
                    borderWidth: 0,
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true, font: { size: 11 } } } }
            }
        }));
    }

    // Recolor grid lines / tick labels instantly when dark mode is toggled,
    // instead of leaving charts with the old theme's colors until reload.
    document.addEventListener('themechange', function (e) {
        var dark = e.detail === 'dark';
        var gc = dark ? 'rgba(255,255,255,.06)' : 'rgba(17,24,39,.06)';
        var tc = dark ? '#A2A6B5' : '#6B7280';
        Chart.defaults.color = tc;
        dashCharts.forEach(function (chart) {
            if (!chart || !chart.options) return;
            if (chart.options.scales) {
                Object.keys(chart.options.scales).forEach(function (axisKey) {
                    var axis = chart.options.scales[axisKey];
                    if (axis.grid) axis.grid.color = gc;
                    if (axis.ticks) axis.ticks.color = tc;
                });
            }
            if (chart.options.plugins && chart.options.plugins.legend && chart.options.plugins.legend.labels) {
                chart.options.plugins.legend.labels.color = tc;
            }
            chart.update();
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
