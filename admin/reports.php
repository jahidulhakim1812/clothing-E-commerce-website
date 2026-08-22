<?php
require_once __DIR__ . '/includes/admin_bootstrap.php';
requireSuperAdmin();
$pageTitle = 'Reports';
require_once __DIR__ . '/includes/admin_header.php';

$reportType = $_GET['type'] ?? 'sales';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
?>

<div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
    <a href="reports.php?type=sales" class="btn btn-sm <?= $reportType === 'sales' ? '' : 'btn-outline' ?>"><i class="fa-solid fa-chart-line"></i> Sales Report</a>
    <a href="reports.php?type=inventory" class="btn btn-sm <?= $reportType === 'inventory' ? '' : 'btn-outline' ?>"><i class="fa-solid fa-warehouse"></i> Inventory Report</a>
    <a href="reports.php?type=orders" class="btn btn-sm <?= $reportType === 'orders' ? '' : 'btn-outline' ?>"><i class="fa-solid fa-cart-shopping"></i> Order Report</a>
    <a href="reports.php?type=profit" class="btn btn-sm <?= $reportType === 'profit' ? '' : 'btn-outline' ?>"><i class="fa-solid fa-sack-dollar"></i> Profit by Product</a>
</div>

<div class="panel">
    <form method="get" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:20px;">
        <input type="hidden" name="type" value="<?= sanitize($reportType) ?>">
        <div class="form-group" style="margin:0;">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= sanitize($startDate) ?>">
        </div>
        <div class="form-group" style="margin:0;">
            <label>End Date</label>
            <input type="date" name="end_date" value="<?= sanitize($endDate) ?>">
        </div>
        <button type="submit" class="btn btn-sm">Filter</button>
    </form>

    <?php if ($reportType === 'sales'):
        $stmt = $pdo->prepare("SELECT DATE(created_at) as d, COUNT(*) as orders_count, SUM(total_amount) as revenue
            FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND order_status != 'cancelled'
            GROUP BY DATE(created_at) ORDER BY d DESC");
        $stmt->execute([$startDate, $endDate]);
        $salesData = $stmt->fetchAll();

        $totalStmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(total_amount),0) as revenue FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND order_status != 'cancelled'");
        $totalStmt->execute([$startDate, $endDate]);
        $totals = $totalStmt->fetch();

        $topProducts = $pdo->prepare("SELECT oi.product_name, SUM(oi.quantity) as total_qty, SUM(oi.line_total) as total_revenue
            FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status != 'cancelled'
            GROUP BY oi.product_name ORDER BY total_qty DESC LIMIT 5");
        $topProducts->execute([$startDate, $endDate]);
        $topProductsData = $topProducts->fetchAll();

        // Product-wise revenue, highest first, top 12 for readability (feeds the revenue line chart)
        $revByProduct = $pdo->prepare("SELECT oi.product_name, SUM(oi.line_total) as total_revenue
            FROM order_items oi JOIN orders o ON o.id = oi.order_id
            WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status != 'cancelled'
            GROUP BY oi.product_name ORDER BY total_revenue DESC LIMIT 12");
        $revByProduct->execute([$startDate, $endDate]);
        $revByProductData = $revByProduct->fetchAll();

        // Chart data — ascending by date for the trend line (table below stays newest-first)
        $salesChron = array_reverse($salesData);
        $salesChartLabels = array_map(function ($r) { return date('d M', strtotime($r['d'])); }, $salesChron);
        $salesChartRevenue = array_map(function ($r) { return (float)$r['revenue']; }, $salesChron);
        $salesChartOrders = array_map(function ($r) { return (int)$r['orders_count']; }, $salesChron);
        $topProductNames = array_map(function ($r) { return $r['product_name']; }, $topProductsData);
        $topProductQty = array_map(function ($r) { return (int)$r['total_qty']; }, $topProductsData);
        $revProductLabels = array_map(function ($r) { return $r['product_name']; }, $revByProductData);
        $revProductValues = array_map(function ($r) { return round((float)$r['total_revenue'], 2); }, $revByProductData);
    ?>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-cart-shopping"></i></div>
                <div><div class="value"><?= (int)$totals['cnt'] ?></div><div class="label">Total Orders</div></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div><div class="value"><?= formatPrice($totals['revenue']) ?></div><div class="label">Total Revenue</div></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-calculator"></i></div>
                <div><div class="value"><?= $totals['cnt'] > 0 ? formatPrice($totals['revenue'] / $totals['cnt']) : formatPrice(0) ?></div><div class="label">Avg. Order Value</div></div>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-header"><h3>Revenue &amp; Orders</h3></div>
                <div class="chart-sub"><?= sanitize(date('d M Y', strtotime($startDate))) ?> &ndash; <?= sanitize(date('d M Y', strtotime($endDate))) ?> &middot; daily breakdown</div>
                <div class="chart-canvas-wrap"><canvas id="salesTrendChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><h3>Top Selling Products</h3></div>
                <div class="chart-sub">By units sold, this period</div>
                <div class="chart-canvas-wrap"><canvas id="topProductsChart"></canvas></div>
            </div>
        </div>

        <div class="chart-card" style="margin-bottom:22px;">
            <div class="chart-header"><h3>Revenue by Product</h3></div>
            <div class="chart-sub"><?= sanitize(date('d M Y', strtotime($startDate))) ?> &ndash; <?= sanitize(date('d M Y', strtotime($endDate))) ?> &middot; total revenue per product, top 12</div>
            <div class="chart-canvas-wrap"><canvas id="revenueByProductChart"></canvas></div>
        </div>

        <h4 style="margin-bottom:12px;">Top Selling Products</h4>
        <div class="table-wrap" style="margin-bottom:24px;">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Units Sold</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($topProductsData as $tp): ?>
                    <tr><td><?= sanitize($tp['product_name']) ?></td><td><?= (int)$tp['total_qty'] ?></td><td><?= formatPrice($tp['total_revenue']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($topProductsData)): ?><tr><td colspan="3" style="text-align:center;color:var(--a-text-light);">No sales in this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <h4 style="margin-bottom:12px;">Daily Breakdown</h4>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($salesData as $row): ?>
                    <tr><td><?= date('d M Y', strtotime($row['d'])) ?></td><td><?= (int)$row['orders_count'] ?></td><td><?= formatPrice($row['revenue']) ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($salesData)): ?><tr><td colspan="3" style="text-align:center;color:var(--a-text-light);">No data for this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'inventory'):
        $products = $pdo->query("SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON c.id=p.category_id ORDER BY p.stock ASC")->fetchAll();
        $totalStockValue = 0;
        $stockByCategory = [];
        $stockStatus = ['Out of Stock' => 0, 'Low Stock' => 0, 'In Stock' => 0];
        foreach ($products as $p) {
            $totalStockValue += $p['stock'] * $p['price'];
            $cat = $p['category_name'];
            $stockByCategory[$cat] = ($stockByCategory[$cat] ?? 0) + (int)$p['stock'];
            if ($p['stock'] <= 0) $stockStatus['Out of Stock']++;
            elseif ($p['stock'] <= 10) $stockStatus['Low Stock']++;
            else $stockStatus['In Stock']++;
        }
        arsort($stockByCategory);
    ?>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-boxes-stacked"></i></div>
                <div><div class="value"><?= count($products) ?></div><div class="label">Total Products</div></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-warehouse"></i></div>
                <div><div class="value"><?= formatPrice($totalStockValue) ?></div><div class="label">Total Stock Value</div></div>
            </div>
        </div>

        <div class="chart-grid">
            <div class="chart-card">
                <div class="chart-header"><h3>Stock by Category</h3></div>
                <div class="chart-sub">Units currently on hand, by category</div>
                <div class="chart-canvas-wrap"><canvas id="stockByCategoryChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><h3>Stock Status</h3></div>
                <div class="chart-sub">Across all products</div>
                <div class="chart-canvas-wrap"><canvas id="stockStatusChart"></canvas></div>
                <ul class="chart-legend-list">
                    <li><span class="label"><span class="dot" style="background:#DC2626;"></span>Out of Stock</span><span class="val"><?= $stockStatus['Out of Stock'] ?></span></li>
                    <li><span class="label"><span class="dot" style="background:#F59E0B;"></span>Low Stock</span><span class="val"><?= $stockStatus['Low Stock'] ?></span></li>
                    <li><span class="label"><span class="dot" style="background:#059669;"></span>In Stock</span><span class="val"><?= $stockStatus['In Stock'] ?></span></li>
                </ul>
            </div>
        </div>

        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Stock Value</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td><?= sanitize($p['name']) ?></td>
                        <td><?= sanitize($p['category_name']) ?></td>
                        <td><?= formatPrice($p['price']) ?></td>
                        <td><?= (int)$p['stock'] ?></td>
                        <td><?= formatPrice($p['stock'] * $p['price']) ?></td>
                        <td>
                            <?php if ($p['stock'] <= 0): ?><span class="status-badge status-inactive">Out of Stock</span>
                            <?php elseif ($p['stock'] <= 10): ?><span class="status-badge status-pending">Low Stock</span>
                            <?php else: ?><span class="status-badge status-active">In Stock</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($reportType === 'orders'):
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE DATE(created_at) BETWEEN ? AND ? ORDER BY created_at DESC");
        $stmt->execute([$startDate, $endDate]);
        $ordersData = $stmt->fetchAll();
        $statusBreakdown = ['pending'=>0,'processing'=>0,'shipped'=>0,'delivered'=>0,'cancelled'=>0];
        $paymentBreakdown = [];
        foreach ($ordersData as $o) {
            $statusBreakdown[$o['order_status']]++;
            $paymentBreakdown[$o['payment_status']] = ($paymentBreakdown[$o['payment_status']] ?? 0) + 1;
        }
        $orderStatusColors = ['pending'=>'#F59E0B','processing'=>'#3B82F6','shipped'=>'#6366F1','delivered'=>'#059669','cancelled'=>'#DC2626'];
        $paymentColors = ['paid'=>'#059669','pending'=>'#F59E0B','failed'=>'#DC2626','refunded'=>'#6B7280'];
        $paymentChartColors = array_map(function ($k) use ($paymentColors) { return $paymentColors[$k] ?? '#818CF8'; }, array_keys($paymentBreakdown));
    ?>
        <div class="stat-grid" style="margin-bottom:20px;">
            <?php foreach ($statusBreakdown as $status => $count): ?>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-circle-notch"></i></div>
                <div><div class="value"><?= $count ?></div><div class="label"><?= ucfirst($status) ?></div></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="chart-grid-2">
            <div class="chart-card">
                <div class="chart-header"><h3>Orders by Status</h3></div>
                <div class="chart-sub"><?= sanitize(date('d M Y', strtotime($startDate))) ?> &ndash; <?= sanitize(date('d M Y', strtotime($endDate))) ?></div>
                <div class="chart-canvas-wrap"><canvas id="ordersStatusChart"></canvas></div>
            </div>
            <div class="chart-card">
                <div class="chart-header"><h3>Orders by Payment Status</h3></div>
                <div class="chart-sub">Same date range</div>
                <div class="chart-canvas-wrap"><canvas id="paymentStatusChart"></canvas></div>
            </div>
        </div>

        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Order #</th><th>Customer</th><th>Total</th><th>Payment</th><th>Order Status</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($ordersData as $o): ?>
                    <tr>
                        <td><?= sanitize($o['order_number']) ?></td>
                        <td><?= sanitize($o['guest_name']) ?></td>
                        <td><?= formatPrice($o['total_amount']) ?></td>
                        <td><span class="status-badge status-<?= sanitize($o['payment_status']) ?>"><?= sanitize($o['payment_status']) ?></span></td>
                        <td><span class="status-badge status-<?= sanitize($o['order_status']) ?>"><?= sanitize($o['order_status']) ?></span></td>
                        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($ordersData)): ?><tr><td colspan="6" style="text-align:center;color:var(--a-text-light);">No orders in this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

    <?php else: // profit report — profit per product = (sale price - cost/purchase price) x units sold
        $profitStmt = $pdo->prepare("SELECT oi.product_name,
                SUM(oi.quantity) AS units_sold,
                SUM(oi.line_total) AS revenue,
                SUM(COALESCE(p.cost_price, 0) * oi.quantity) AS cost,
                SUM(oi.line_total) - SUM(COALESCE(p.cost_price, 0) * oi.quantity) AS profit
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status != 'cancelled'
            GROUP BY oi.product_name
            ORDER BY profit DESC");
        $profitStmt->execute([$startDate, $endDate]);
        $profitData = $profitStmt->fetchAll();

        $totalRevenue = 0; $totalCost = 0; $totalProfit = 0;
        foreach ($profitData as $row) {
            $totalRevenue += (float)$row['revenue'];
            $totalCost += (float)$row['cost'];
            $totalProfit += (float)$row['profit'];
        }

        // Chart data, sorted by highest profit first, top 12 products for readability
        $profitChartRows = array_slice($profitData, 0, 12);
        $profitChartLabels = array_map(function ($r) { return $r['product_name']; }, $profitChartRows);
        $profitChartValues = array_map(function ($r) { return round((float)$r['profit'], 2); }, $profitChartRows);
        $profitChartColors = array_map(function ($v) { return $v >= 0 ? '#059669' : '#DC2626'; }, $profitChartValues);
    ?>
        <div class="stat-grid" style="margin-bottom:20px;">
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div><div class="value"><?= formatPrice($totalRevenue) ?></div><div class="label">Total Sale Amount</div></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-money-bill-transfer"></i></div>
                <div><div class="value"><?= formatPrice($totalCost) ?></div><div class="label">Total Purchase Amount</div></div>
            </div>
            <div class="stat-card">
                <div class="icon"><i class="fa-solid fa-chart-line"></i></div>
                <div><div class="value"><?= formatPrice($totalProfit) ?></div><div class="label">Total Profit</div></div>
            </div>
        </div>

        <div class="chart-card" style="margin-bottom:20px;">
            <div class="chart-header"><h3>Profit by Product</h3></div>
            <div class="chart-sub"><?= sanitize(date('d M Y', strtotime($startDate))) ?> &ndash; <?= sanitize(date('d M Y', strtotime($endDate))) ?> &middot; sale price minus purchase (cost) price, top 12 by profit</div>
            <div class="chart-canvas-wrap" style="height:<?= max(320, count($profitChartLabels) * 34) ?>px;"><canvas id="profitByProductChart"></canvas></div>
        </div>

        <h4 style="margin-bottom:12px;">Profit Breakdown, All Products</h4>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Product</th><th>Units Sold</th><th>Sale Amount</th><th>Purchase Amount</th><th>Profit</th></tr></thead>
                <tbody>
                <?php foreach ($profitData as $row): ?>
                    <tr>
                        <td><?= sanitize($row['product_name']) ?></td>
                        <td><?= (int)$row['units_sold'] ?></td>
                        <td><?= formatPrice($row['revenue']) ?></td>
                        <td><?= formatPrice($row['cost']) ?></td>
                        <td style="color:<?= $row['profit'] >= 0 ? '#059669' : '#DC2626' ?>;font-weight:600;"><?= formatPrice($row['profit']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($profitData)): ?><tr><td colspan="5" style="text-align:center;color:var(--a-text-light);">No sales in this period.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script src="../assets/js/vendor/chartjs/chart.umd.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') {
        console.error('Chart.js did not load — report charts cannot render.');
        return;
    }
    var isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    var gridColor = isDark ? 'rgba(255,255,255,.06)' : 'rgba(17,24,39,.06)';
    var tickColor = isDark ? '#A2A6B5' : '#6B7280';
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = tickColor;
    var currency = '<?= sanitize(setting("currency_symbol", "৳")) ?>';

    <?php if ($reportType === 'sales'): ?>
    var salesTrendCanvas = document.getElementById('salesTrendChart');
    if (salesTrendCanvas) {
        new Chart(salesTrendCanvas, {
            type: 'line',
            data: {
                labels: <?= json_encode($salesChartLabels) ?>,
                datasets: [
                    {
                        label: 'Revenue', yAxisID: 'y',
                        data: <?= json_encode($salesChartRevenue) ?>,
                        borderColor: '#4F46E5', backgroundColor: 'rgba(79,70,229,.12)',
                        fill: true, tension: 0.35, pointRadius: 2, borderWidth: 2.5
                    },
                    {
                        label: 'Orders', yAxisID: 'y1',
                        data: <?= json_encode($salesChartOrders) ?>,
                        borderColor: '#F59E0B', backgroundColor: 'rgba(245,158,11,.12)',
                        fill: false, tension: 0.35, pointRadius: 2, borderWidth: 2.5, borderDash: [4, 3]
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top', align: 'end', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } },
                scales: {
                    x: { grid: { display: false } },
                    y: { position: 'left', grid: { color: gridColor }, ticks: { callback: function (v) { return currency + v; } } },
                    y1: { position: 'right', grid: { display: false }, ticks: { stepSize: 1, precision: 0 } }
                }
            }
        });
    }
    var topProductsCanvas = document.getElementById('topProductsChart');
    if (topProductsCanvas) {
        new Chart(topProductsCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($topProductNames) ?>,
                datasets: [{
                    label: 'Units Sold',
                    data: <?= json_encode($topProductQty) ?>,
                    backgroundColor: '#818CF8', borderRadius: 6, maxBarThickness: 30
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { stepSize: 1, precision: 0 } },
                    y: { grid: { display: false } }
                }
            }
        });
    }
    var revenueByProductCanvas = document.getElementById('revenueByProductChart');
    if (revenueByProductCanvas) {
        new Chart(revenueByProductCanvas, {
            type: 'line',
            data: {
                labels: <?= json_encode($revProductLabels) ?>,
                datasets: [{
                    label: 'Revenue',
                    data: <?= json_encode($revProductValues) ?>,
                    borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.12)',
                    fill: true, tension: 0.3, pointRadius: 3, pointBackgroundColor: '#059669', borderWidth: 2.5
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (ctx) { return 'Revenue: ' + currency + ctx.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } } }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 40, minRotation: 40 } },
                    y: { grid: { color: gridColor }, ticks: { callback: function (v) { return currency + v; } } }
                }
            }
        });
    }
    <?php elseif ($reportType === 'inventory'): ?>
    var stockCatCanvas = document.getElementById('stockByCategoryChart');
    if (stockCatCanvas) {
        new Chart(stockCatCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_keys($stockByCategory)) ?>,
                datasets: [{
                    label: 'Units in Stock',
                    data: <?= json_encode(array_values($stockByCategory)) ?>,
                    backgroundColor: '#4F46E5', borderRadius: 6, maxBarThickness: 36
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
        });
    }
    var stockStatusCanvas = document.getElementById('stockStatusChart');
    if (stockStatusCanvas) {
        new Chart(stockStatusCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_keys($stockStatus)) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($stockStatus)) ?>,
                    backgroundColor: ['#DC2626', '#F59E0B', '#059669'],
                    borderWidth: 0
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, cutout: '68%', plugins: { legend: { display: false } } }
        });
    }
    <?php elseif ($reportType === 'orders'): ?>
    var ordersStatusCanvas = document.getElementById('ordersStatusChart');
    if (ordersStatusCanvas) {
        new Chart(ordersStatusCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map('ucfirst', array_keys($statusBreakdown))) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($statusBreakdown)) ?>,
                    backgroundColor: <?= json_encode(array_values($orderStatusColors)) ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } }
            }
        });
    }
    var paymentStatusCanvas = document.getElementById('paymentStatusChart');
    if (paymentStatusCanvas) {
        new Chart(paymentStatusCanvas, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_map('ucfirst', array_keys($paymentBreakdown))) ?>,
                datasets: [{
                    data: <?= json_encode(array_values($paymentBreakdown)) ?>,
                    backgroundColor: <?= json_encode($paymentChartColors) ?>,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false, cutout: '68%',
                plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, boxHeight: 10, usePointStyle: true } } }
            }
        });
    }
    <?php else: ?>
    var profitByProductCanvas = document.getElementById('profitByProductChart');
    if (profitByProductCanvas) {
        new Chart(profitByProductCanvas, {
            type: 'bar',
            data: {
                labels: <?= json_encode($profitChartLabels) ?>,
                datasets: [{
                    label: 'Profit',
                    data: <?= json_encode($profitChartValues) ?>,
                    backgroundColor: <?= json_encode($profitChartColors) ?>,
                    borderRadius: 6, maxBarThickness: 26
                }]
            },
            options: {
                indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: function (ctx) { return 'Profit: ' + currency + ctx.parsed.x.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); } } }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { callback: function (v) { return currency + v; } } },
                    y: { grid: { display: false } }
                }
            }
        });
    }
    <?php endif; ?>
})();
</script>

<?php require_once __DIR__ . '/includes/admin_footer.php'; ?>
