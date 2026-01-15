/**
 * Financial Reports JavaScript
 *
 * Handles chart rendering and data fetching for financial reports.
 */

(function ($) {
    "use strict";

    let revenueChart, agingChart, fundingChart;
    let currentTimePeriod = "monthly";

    $(document).ready(function () {
        initializeDatePickers();
        loadReportsData();
        setupEventHandlers();
    });

    /**
     * Initialize date pickers
     */
    function initializeDatePickers() {
        $(".report-datepicker").datepicker({
            dateFormat: "yy-mm-dd",
            maxDate: 0, // Today
            changeMonth: true,
            changeYear: true,
            yearRange: "-10:+0",
        });
    }

    /**
     * Setup event handlers
     */
    function setupEventHandlers() {
        // Refresh button
        $("#refresh-reports-btn").on("click", function () {
            loadReportsData();
        });

        // Time period buttons for revenue chart
        $(".time-period-btn").on("click", function () {
            $(".time-period-btn").removeClass("active");
            $(this).addClass("active");
            currentTimePeriod = $(this).data("period");
            loadRevenueChart();
        });

        // Export Excel button
        $("#export-excel-btn").on("click", function () {
            exportToExcel();
        });

        // Export BPF button
        $("#export-bpf-btn").on("click", function () {
            exportBPF();
        });
    }

    /**
     * Load all reports data
     */
    function loadReportsData() {
        const startDate = $("#report-start-date").val();
        const endDate = $("#report-end-date").val();

        // Show spinners
        $(".summary-value").html('<span class="spinner is-active"></span>');

        // Load summary stats
        loadSummaryStats(startDate, endDate);

        // Load charts
        loadRevenueChart();
        loadAgingChart();
        loadFundingChart();
    }

    /**
     * Load summary statistics
     */
    function loadSummaryStats(startDate, endDate) {
        $.ajax({
            url: formapressReports.ajax_url,
            type: "POST",
            data: {
                action: "formapress_get_summary_stats",
                nonce: formapressReports.nonce,
                start_date: startDate,
                end_date: endDate,
            },
            success: function (response) {
                if (response.success) {
                    const stats = response.data;
                    $("#total-revenue").text(formatCurrency(stats.total_revenue));
                    $("#paid-invoices").html(`<strong>${stats.paid_count}</strong> / ${stats.total_count} factures`);
                    $("#outstanding-balance").text(formatCurrency(stats.outstanding));
                    $("#overdue-amount").text(formatCurrency(stats.overdue));
                } else {
                    console.error("Failed to load summary stats:", response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error loading summary stats:", error);
            },
        });
    }

    /**
     * Load revenue over time chart
     */
    function loadRevenueChart() {
        const startDate = $("#report-start-date").val();
        const endDate = $("#report-end-date").val();

        $.ajax({
            url: formapressReports.ajax_url,
            type: "POST",
            data: {
                action: "formapress_get_revenue_data",
                nonce: formapressReports.nonce,
                start_date: startDate,
                end_date: endDate,
                period: currentTimePeriod,
            },
            success: function (response) {
                if (response.success) {
                    renderRevenueChart(response.data);
                } else {
                    console.error("Failed to load revenue data:", response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error loading revenue data:", error);
            },
        });
    }

    /**
     * Load aging analysis chart
     */
    function loadAgingChart() {
        $.ajax({
            url: formapressReports.ajax_url,
            type: "POST",
            data: {
                action: "formapress_get_aging_data",
                nonce: formapressReports.nonce,
            },
            success: function (response) {
                if (response.success) {
                    renderAgingChart(response.data);
                } else {
                    console.error("Failed to load aging data:", response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error loading aging data:", error);
            },
        });
    }

    /**
     * Load funding source chart
     */
    function loadFundingChart() {
        const startDate = $("#report-start-date").val();
        const endDate = $("#report-end-date").val();

        $.ajax({
            url: formapressReports.ajax_url,
            type: "POST",
            data: {
                action: "formapress_get_funding_data",
                nonce: formapressReports.nonce,
                start_date: startDate,
                end_date: endDate,
            },
            success: function (response) {
                if (response.success) {
                    renderFundingChart(response.data);
                } else {
                    console.error("Failed to load funding data:", response.data.message);
                }
            },
            error: function (xhr, status, error) {
                console.error("AJAX error loading funding data:", error);
            },
        });
    }

    /**
     * Render revenue chart
     */
    function renderRevenueChart(data) {
        const ctx = document.getElementById("revenue-chart");

        if (revenueChart) {
            revenueChart.destroy();
        }

        revenueChart = new Chart(ctx, {
            type: "line",
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: "Chiffre d'affaires",
                        data: data.values,
                        borderColor: "rgb(255, 140, 0)",
                        backgroundColor: "rgba(255, 140, 0, 0.1)",
                        fill: true,
                        tension: 0.4,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return formatCurrency(context.parsed.y);
                            },
                        },
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return formatCurrency(value);
                            },
                        },
                    },
                },
            },
        });
    }

    /**
     * Render aging analysis chart
     */
    function renderAgingChart(data) {
        const ctx = document.getElementById("aging-chart");

        if (agingChart) {
            agingChart.destroy();
        }

        agingChart = new Chart(ctx, {
            type: "bar",
            data: {
                labels: data.labels,
                datasets: [
                    {
                        label: "Montant impayé",
                        data: data.values,
                        backgroundColor: [
                            "rgba(52, 168, 83, 0.7)",
                            "rgba(251, 188, 5, 0.7)",
                            "rgba(255, 140, 0, 0.7)",
                            "rgba(234, 67, 53, 0.7)",
                        ],
                    },
                ],
            },
            options: {
                indexAxis: "y",
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false,
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return formatCurrency(context.parsed.x);
                            },
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (value) {
                                return formatCurrency(value);
                            },
                        },
                    },
                },
            },
        });
    }

    /**
     * Render funding source chart
     */
    function renderFundingChart(data) {
        const ctx = document.getElementById("funding-chart");

        if (fundingChart) {
            fundingChart.destroy();
        }

        fundingChart = new Chart(ctx, {
            type: "doughnut",
            data: {
                labels: data.labels,
                datasets: [
                    {
                        data: data.values,
                        backgroundColor: [
                            "rgba(255, 140, 0, 0.8)",
                            "rgba(52, 168, 83, 0.8)",
                            "rgba(66, 133, 244, 0.8)",
                            "rgba(251, 188, 5, 0.8)",
                            "rgba(234, 67, 53, 0.8)",
                        ],
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: "right",
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const label = context.label || "";
                                const value = formatCurrency(context.parsed);
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.parsed / total) * 100).toFixed(1);
                                return `${label}: ${value} (${percentage}%)`;
                            },
                        },
                    },
                },
            },
        });
    }

    /**
     * Export to Excel
     */
    function exportToExcel() {
        const startDate = $("#report-start-date").val();
        const endDate = $("#report-end-date").val();

        window.location.href =
            formapressReports.ajax_url +
            "?action=formapress_export_excel" +
            "&nonce=" +
            formapressReports.nonce +
            "&start_date=" +
            startDate +
            "&end_date=" +
            endDate;
    }

    /**
     * Export BPF
     */
    function exportBPF() {
        const startDate = $("#report-start-date").val();
        const endDate = $("#report-end-date").val();

        window.location.href =
            formapressReports.ajax_url +
            "?action=formapress_export_bpf" +
            "&nonce=" +
            formapressReports.nonce +
            "&start_date=" +
            startDate +
            "&end_date=" +
            endDate;
    }

    /**
     * Format currency
     */
    function formatCurrency(amount) {
        return new Intl.NumberFormat("fr-FR", {
            style: "currency",
            currency: "EUR",
        }).format(amount);
    }
})(jQuery);
