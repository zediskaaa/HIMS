/**
 * Demand Forecast Dashboard — Chart.js + What-If Simulation
 *
 * Registers an Alpine.js component `forecastDashboardCharts` that drives:
 *   1. A Chart.js time-series line chart with actual/forecast/confidence bands
 *   2. A feature-importance horizontal bar chart
 *   3. What-If sliders for Lead Time and Demand Spike simulation
 *   4. Manual override modal state management
 *
 * This file is loaded inline on the demand forecast page via a Vite import.
 * It does NOT modify the existing `demandForecastDashboard` Alpine component
 * in app.js — it is a companion for the dedicated forecast page.
 */
import {
    Chart,
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
    Legend,
    BarController,
    BarElement,
} from 'chart.js';

Chart.register(
    LineController,
    LineElement,
    PointElement,
    LinearScale,
    CategoryScale,
    Filler,
    Tooltip,
    Legend,
    BarController,
    BarElement,
);

/**
 * Mock time-series data for chart demonstration.
 * In production this would come from a DemandForecastService endpoint.
 */
const generateMockTimeSeriesData = (horizon = '30D') => {
    const today = new Date();
    const dayMs = 86400000;

    const historyDays = { '7D': 14, '30D': 60, '90D': 120 }[horizon] || 60;
    const forecastDays = { '7D': 7, '30D': 30, '90D': 90 }[horizon] || 30;

    const labels = [];
    const actual = [];
    const forecast = [];
    const p10 = [];
    const p90 = [];

    // Historical data (actual consumption)
    for (let i = historyDays; i > 0; i--) {
        const date = new Date(today.getTime() - i * dayMs);
        labels.push(formatChartDate(date));
        // Simulate seasonal pattern with noise
        const base = 45 + 15 * Math.sin((i / 30) * Math.PI);
        actual.push(Math.round(base + (Math.random() - 0.5) * 20));
        forecast.push(null);
        p10.push(null);
        p90.push(null);
    }

    // Transition point (today)
    const lastActual = actual[actual.length - 1];
    labels.push(formatChartDate(today));
    actual.push(lastActual);
    forecast.push(lastActual);
    p10.push(lastActual);
    p90.push(lastActual);

    // Forecast data (projected)
    const trendSlope = 0.3;
    for (let i = 1; i <= forecastDays; i++) {
        const date = new Date(today.getTime() + i * dayMs);
        labels.push(formatChartDate(date));
        actual.push(null);
        const base = lastActual + trendSlope * i + 5 * Math.sin((i / 15) * Math.PI);
        const forecastVal = Math.round(base);
        forecast.push(forecastVal);
        const spread = 5 + i * 0.4;
        p10.push(Math.round(forecastVal - spread));
        p90.push(Math.round(forecastVal + spread));
    }

    return { labels, actual, forecast, p10, p90, safetyStock: 25, reorderPoint: 35 };
};

/**
 * Mock SKU inventory data for the recommendations table.
 */
const generateMockInventoryData = () => [
    {
        id: 1, sku: 'MED-001', name: 'Paracetamol 500mg Tab', category: 'Pharmaceuticals',
        currentStock: 1200, projectedDemand: 2800, daysOfInventory: 4, confidence: 'high',
        recommendation: 'urgent', suggestedQty: 3500, trend: 12.5,
        avgDailyUsage: 45, leadTimeDays: 7, safetyStock: 315,
    },
    {
        id: 2, sku: 'MED-002', name: 'Amoxicillin 250mg Cap', category: 'Antibiotics',
        currentStock: 850, projectedDemand: 1600, daysOfInventory: 8, confidence: 'high',
        recommendation: 'reorder', suggestedQty: 2000, trend: 8.3,
        avgDailyUsage: 28, leadTimeDays: 10, safetyStock: 196,
    },
    {
        id: 3, sku: 'SUP-010', name: 'Surgical Gloves (L)', category: 'Supplies',
        currentStock: 5000, projectedDemand: 3200, daysOfInventory: 22, confidence: 'medium',
        recommendation: 'sufficient', suggestedQty: 0, trend: -3.1,
        avgDailyUsage: 150, leadTimeDays: 5, safetyStock: 1050,
    },
    {
        id: 4, sku: 'SUP-015', name: 'IV Cannula 20G', category: 'Supplies',
        currentStock: 300, projectedDemand: 900, daysOfInventory: 5, confidence: 'medium',
        recommendation: 'reorder', suggestedQty: 1200, trend: 15.7,
        avgDailyUsage: 18, leadTimeDays: 14, safetyStock: 126,
    },
    {
        id: 5, sku: 'MED-008', name: 'Omeprazole 20mg Cap', category: 'Pharmaceuticals',
        currentStock: 4500, projectedDemand: 1800, daysOfInventory: 45, confidence: 'high',
        recommendation: 'reduce', suggestedQty: 0, trend: -8.2,
        avgDailyUsage: 22, leadTimeDays: 7, safetyStock: 154,
    },
    {
        id: 6, sku: 'MED-012', name: 'Metformin 500mg Tab', category: 'Pharmaceuticals',
        currentStock: 200, projectedDemand: 1400, daysOfInventory: 3, confidence: 'low',
        recommendation: 'urgent', suggestedQty: 2500, trend: 22.1,
        avgDailyUsage: 35, leadTimeDays: 12, safetyStock: 245,
    },
    {
        id: 7, sku: 'SUP-022', name: 'Sterile Gauze 4x4', category: 'Supplies',
        currentStock: 3800, projectedDemand: 2000, daysOfInventory: 28, confidence: 'high',
        recommendation: 'sufficient', suggestedQty: 0, trend: 1.5,
        avgDailyUsage: 65, leadTimeDays: 5, safetyStock: 455,
    },
    {
        id: 8, sku: 'MED-019', name: 'Ceftriaxone 1g Inj', category: 'Antibiotics',
        currentStock: 150, projectedDemand: 600, daysOfInventory: 6, confidence: 'medium',
        recommendation: 'reorder', suggestedQty: 800, trend: 18.9,
        avgDailyUsage: 12, leadTimeDays: 14, safetyStock: 84,
    },
];

function formatChartDate(date) {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

/**
 * HIMS color tokens mapped to Chart.js RGBA.
 */
const COLORS = {
    primary: { line: 'rgb(28, 117, 245)', fill: 'rgba(28, 117, 245, 0.08)' },
    forecast: { line: 'rgb(139, 92, 246)', fill: 'rgba(139, 92, 246, 0.06)' },
    band: { fill: 'rgba(139, 92, 246, 0.10)' },
    safety: 'rgba(239, 68, 68, 0.5)',
    reorder: 'rgba(245, 158, 11, 0.5)',
    grid: 'rgba(0, 0, 0, 0.04)',
    featureSeasonality: 'rgb(28, 117, 245)',
    featureLeadTime: 'rgb(245, 158, 11)',
    featureTrend: 'rgb(34, 197, 94)',
};

export default function forecastDashboardCharts() {
    return {
        // Chart instances
        demandChart: null,
        importanceChart: null,

        // State
        timeHorizon: '30D',
        selectedSku: '',
        leadTimeAdjust: 0,
        demandSpikeAdjust: 0,
        mockData: null,
        inventoryItems: [],

        // Override modal state
        overrideItem: null,
        overrideQty: '',
        overrideReason: '',
        overrideNotes: '',

        // Generate PO modal state
        poItem: null,

        init() {
            this.inventoryItems = generateMockInventoryData();
            this.mockData = generateMockTimeSeriesData(this.timeHorizon);

            this.$nextTick(() => {
                this.initDemandChart();
                this.initImportanceChart();
            });

            this.$watch('timeHorizon', () => {
                this.mockData = generateMockTimeSeriesData(this.timeHorizon);
                this.updateDemandChart();
            });

            this.$watch('selectedSku', () => {
                this.updateDemandChart();
            });

            this.$watch('leadTimeAdjust', () => {
                this.updateDemandChart();
            });

            this.$watch('demandSpikeAdjust', () => {
                this.updateDemandChart();
            });
        },

        // ─── KPI Computations ────────────────────────────────────

        totalProjectedDemand() {
            return this.inventoryItems.reduce((sum, item) => sum + item.projectedDemand, 0);
        },

        avgTrend() {
            const items = this.inventoryItems;
            if (items.length === 0) return 0;
            return items.reduce((sum, item) => sum + item.trend, 0) / items.length;
        },

        stockoutRiskCount() {
            return this.inventoryItems.filter(
                (item) => item.daysOfInventory <= 7,
            ).length;
        },

        capitalAtRisk() {
            // Sum excess stock value for items with DOI > 30 (rough estimate)
            return this.inventoryItems
                .filter((item) => item.daysOfInventory > 30)
                .reduce((sum, item) => {
                    const excess = item.currentStock - item.projectedDemand;
                    return sum + Math.max(0, excess) * 15; // ₱15 per unit estimate
                }, 0);
        },

        aiConfidenceScore() {
            const items = this.inventoryItems;
            if (items.length === 0) return 0;
            const scores = { high: 92, medium: 70, low: 45 };
            const total = items.reduce((sum, item) => sum + (scores[item.confidence] || 50), 0);
            return Math.round(total / items.length);
        },

        confidenceLevel() {
            const score = this.aiConfidenceScore();
            if (score >= 80) return 'High';
            if (score >= 50) return 'Medium';
            return 'Low';
        },

        confidenceTone() {
            const score = this.aiConfidenceScore();
            if (score >= 80) return 'success';
            if (score >= 50) return 'warning';
            return 'danger';
        },

        // ─── What-If Computations ────────────────────────────────

        projectedStockoutDate() {
            // Simulate based on highest-risk item
            const urgentItems = [...this.inventoryItems]
                .filter((item) => item.recommendation === 'urgent' || item.recommendation === 'reorder')
                .sort((a, b) => a.daysOfInventory - b.daysOfInventory);

            if (urgentItems.length === 0) return 'No stockout risk projected';

            const item = urgentItems[0];
            const adjustedDailyUsage = item.avgDailyUsage * (1 + this.demandSpikeAdjust / 100);
            const adjustedLeadTime = Math.max(1, item.leadTimeDays + this.leadTimeAdjust);
            const daysUntilStockout = adjustedDailyUsage > 0
                ? Math.floor(item.currentStock / adjustedDailyUsage)
                : 999;

            const stockoutDate = new Date();
            stockoutDate.setDate(stockoutDate.getDate() + daysUntilStockout);

            const needsReorderBy = new Date();
            needsReorderBy.setDate(needsReorderBy.getDate() + Math.max(0, daysUntilStockout - adjustedLeadTime));

            if (daysUntilStockout <= adjustedLeadTime) {
                return `⚠️ ${item.name} — stockout in ${daysUntilStockout} days (${stockoutDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}). Order immediately!`;
            }

            return `${item.name} — order by ${needsReorderBy.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} to avoid stockout on ${stockoutDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} (${daysUntilStockout} days).`;
        },

        whatIfAlertVariant() {
            const urgentItems = this.inventoryItems.filter(
                (item) => item.recommendation === 'urgent' || item.recommendation === 'reorder',
            );
            if (urgentItems.length === 0) return 'success';

            const item = [...urgentItems].sort((a, b) => a.daysOfInventory - b.daysOfInventory)[0];
            const adjustedDailyUsage = item.avgDailyUsage * (1 + this.demandSpikeAdjust / 100);
            const adjustedLeadTime = Math.max(1, item.leadTimeDays + this.leadTimeAdjust);
            const daysUntilStockout = adjustedDailyUsage > 0
                ? Math.floor(item.currentStock / adjustedDailyUsage)
                : 999;

            if (daysUntilStockout <= adjustedLeadTime) return 'danger';
            if (daysUntilStockout <= adjustedLeadTime * 2) return 'warning';
            return 'info';
        },

        // ─── Chart Initialization ────────────────────────────────

        initDemandChart() {
            const canvas = this.$refs.demandChartCanvas;
            if (!canvas) return;

            const data = this.getChartDatasets();

            this.demandChart = new Chart(canvas, {
                type: 'line',
                data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                usePointStyle: true,
                                pointStyle: 'line',
                                padding: 16,
                                font: { family: 'Inter, sans-serif', size: 11 },
                                color: '#525252',
                            },
                        },
                        tooltip: {
                            backgroundColor: 'rgba(23, 23, 23, 0.92)',
                            titleFont: { family: 'Inter, sans-serif', size: 12 },
                            bodyFont: { family: 'Inter, sans-serif', size: 11 },
                            padding: 10,
                            cornerRadius: 6,
                            displayColors: true,
                            callbacks: {
                                label: (ctx) => {
                                    if (ctx.raw == null) return null;
                                    return ` ${ctx.dataset.label}: ${ctx.raw.toLocaleString()} units`;
                                },
                            },
                        },
                    },
                    scales: {
                        x: {
                            grid: { color: COLORS.grid, drawBorder: false },
                            ticks: {
                                font: { family: 'Inter, sans-serif', size: 10 },
                                color: '#a3a3a3',
                                maxTicksLimit: 12,
                                maxRotation: 0,
                            },
                        },
                        y: {
                            beginAtZero: true,
                            grid: { color: COLORS.grid, drawBorder: false },
                            ticks: {
                                font: { family: 'Inter, sans-serif', size: 10 },
                                color: '#a3a3a3',
                                callback: (val) => val.toLocaleString(),
                            },
                        },
                    },
                    elements: {
                        point: { radius: 0, hoverRadius: 4, hoverBorderWidth: 2 },
                        line: { tension: 0.3 },
                    },
                },
            });
        },

        getChartDatasets() {
            const data = this.mockData;
            const spikeMultiplier = 1 + this.demandSpikeAdjust / 100;

            const adjustedForecast = data.forecast.map((v) => (v != null ? Math.round(v * spikeMultiplier) : null));
            const adjustedP10 = data.p10.map((v) => (v != null ? Math.round(v * spikeMultiplier) : null));
            const adjustedP90 = data.p90.map((v) => (v != null ? Math.round(v * spikeMultiplier) : null));

            const adjustedSafety = Math.round(data.safetyStock * (1 + this.demandSpikeAdjust / 200));
            const adjustedReorder = Math.round(
                data.reorderPoint * (1 + this.demandSpikeAdjust / 200)
                + this.leadTimeAdjust * 3,
            );

            return {
                labels: data.labels,
                datasets: [
                    {
                        label: 'Actual Consumption',
                        data: data.actual,
                        borderColor: COLORS.primary.line,
                        backgroundColor: COLORS.primary.fill,
                        borderWidth: 2,
                        fill: false,
                        spanGaps: false,
                    },
                    {
                        label: 'AI Forecast',
                        data: adjustedForecast,
                        borderColor: COLORS.forecast.line,
                        backgroundColor: COLORS.forecast.fill,
                        borderWidth: 2,
                        borderDash: [6, 4],
                        fill: false,
                        spanGaps: false,
                    },
                    {
                        label: 'P90 Upper Bound',
                        data: adjustedP90,
                        borderColor: 'transparent',
                        backgroundColor: COLORS.band.fill,
                        borderWidth: 0,
                        fill: '+1',
                        pointRadius: 0,
                        spanGaps: false,
                    },
                    {
                        label: 'P10 Lower Bound',
                        data: adjustedP10,
                        borderColor: 'transparent',
                        backgroundColor: COLORS.band.fill,
                        borderWidth: 0,
                        fill: false,
                        pointRadius: 0,
                        spanGaps: false,
                    },
                    {
                        label: 'Safety Stock',
                        data: Array(data.labels.length).fill(adjustedSafety),
                        borderColor: COLORS.safety,
                        borderWidth: 1.5,
                        borderDash: [4, 4],
                        pointRadius: 0,
                        fill: false,
                    },
                    {
                        label: 'Reorder Point',
                        data: Array(data.labels.length).fill(adjustedReorder),
                        borderColor: COLORS.reorder,
                        borderWidth: 1.5,
                        borderDash: [8, 4],
                        pointRadius: 0,
                        fill: false,
                    },
                ],
            };
        },

        updateDemandChart() {
            if (!this.demandChart) return;

            if (!this.mockData) {
                this.mockData = generateMockTimeSeriesData(this.timeHorizon);
            }

            const data = this.getChartDatasets();
            this.demandChart.data = data;
            this.demandChart.update('none');
        },

        initImportanceChart() {
            const canvas = this.$refs.importanceChartCanvas;
            if (!canvas) return;

            this.importanceChart = new Chart(canvas, {
                type: 'bar',
                data: {
                    labels: ['Seasonality', 'Lead Time', 'Trend', 'Volume', 'Supplier'],
                    datasets: [{
                        data: [0.35, 0.25, 0.20, 0.12, 0.08],
                        backgroundColor: [
                            COLORS.featureSeasonality,
                            COLORS.featureLeadTime,
                            COLORS.featureTrend,
                            'rgb(139, 92, 246)',
                            'rgb(163, 163, 163)',
                        ],
                        borderRadius: 4,
                        barPercentage: 0.6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    indexAxis: 'y',
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(23, 23, 23, 0.92)',
                            titleFont: { family: 'Inter, sans-serif', size: 11 },
                            bodyFont: { family: 'Inter, sans-serif', size: 11 },
                            cornerRadius: 6,
                            callbacks: {
                                label: (ctx) => ` Impact: ${(ctx.raw * 100).toFixed(0)}%`,
                            },
                        },
                    },
                    scales: {
                        x: {
                            max: 0.5,
                            grid: { color: COLORS.grid, drawBorder: false },
                            ticks: {
                                font: { family: 'Inter, sans-serif', size: 10 },
                                color: '#a3a3a3',
                                callback: (val) => `${(val * 100).toFixed(0)}%`,
                            },
                        },
                        y: {
                            grid: { display: false },
                            ticks: {
                                font: { family: 'Inter, sans-serif', size: 11 },
                                color: '#404040',
                            },
                        },
                    },
                },
            });
        },

        // ─── Recommendation Helpers ──────────────────────────────

        badgeVariant(rec) {
            return { urgent: 'danger', reorder: 'warning', reduce: 'primary', sufficient: 'success' }[rec] || 'neutral';
        },

        badgeLabel(rec) {
            return { urgent: 'Urgent', reorder: 'Reorder Now', reduce: 'Reduce Order', sufficient: 'Sufficient' }[rec] || rec;
        },

        doiColorClass(doi) {
            if (doi <= 5) return 'text-danger-700 font-semibold';
            if (doi <= 14) return 'text-warning-700 font-semibold';
            return 'text-neutral-800';
        },

        // ─── Override Modal ──────────────────────────────────────

        openOverride(item) {
            this.overrideItem = item;
            this.overrideQty = String(item.suggestedQty);
            this.overrideReason = '';
            this.overrideNotes = '';
            this.$dispatch('open-modal', 'manual-override');
        },

        submitOverride() {
            if (!this.overrideItem || !this.overrideReason) return;

            // Apply override client-side
            const item = this.inventoryItems.find((i) => i.id === this.overrideItem.id);
            if (item) {
                item.suggestedQty = Number(this.overrideQty) || 0;
                item.recommendation = item.suggestedQty > 0 ? 'reorder' : 'sufficient';
            }

            this.$dispatch('close-modal', 'manual-override');
            this.overrideItem = null;
        },

        // ─── Generate PO Modal ───────────────────────────────────

        openGeneratePO(item) {
            this.poItem = item;
            this.$dispatch('open-modal', 'generate-po');
        },

        // ─── Format Helpers ──────────────────────────────────────

        formatNumber(value) {
            return Number(value || 0).toLocaleString();
        },

        formatCurrency(value) {
            return '₱' + Number(value || 0).toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },

        // ─── Cleanup ─────────────────────────────────────────────

        destroy() {
            this.demandChart?.destroy();
            this.importanceChart?.destroy();
        },
    };
}

// Self-register with the global Alpine instance. Alpine.data() works even
// after Alpine.start() in Alpine.js v3, so this separate entry point can
// load asynchronously without racing the main app.js initialization.
if (window.Alpine) {
    window.Alpine.data('forecastDashboardCharts', forecastDashboardCharts);
}
