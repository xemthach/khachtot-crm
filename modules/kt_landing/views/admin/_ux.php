<?php defined('BASEPATH') or exit('No direct script access allowed'); ?>
<style>
    .kt-cms-shell { display: grid; gap: 16px; }
    .kt-cms-hero {
        background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
        border: 1px solid #dbe7f3;
        border-radius: 18px;
        padding: 18px;
        box-shadow: 0 12px 28px rgba(15, 23, 42, .04);
    }
    .kt-cms-hero h3,
    .kt-cms-hero h4 {
        margin: 0 0 6px;
        font-weight: 800;
        color: #0f172a;
    }
    .kt-cms-subtitle { color: #64748b; margin: 0; }
    .kt-cms-kpis { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .kt-cms-kpi {
        background: #fff;
        border: 1px solid #dbe7f3;
        border-radius: 16px;
        padding: 14px 16px;
        min-width: 0;
    }
    .kt-cms-kpi span {
        display: block;
        font-size: 11px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .06em;
        color: #64748b;
    }
    .kt-cms-kpi strong {
        display: block;
        margin-top: 6px;
        font-size: 24px;
        line-height: 1.1;
        color: #0f172a;
        overflow-wrap: anywhere;
    }
    .kt-cms-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 16px;
    }
    .kt-cms-card,
    .kt-cms-panel {
        background: #fff;
        border: 1px solid #dde7f1;
        border-radius: 18px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        min-width: 0;
    }
    .kt-cms-card { padding: 16px; }
    .kt-cms-card h5,
    .kt-cms-panel h5 {
        margin: 0 0 10px;
        font-size: 13px;
        font-weight: 800;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: .05em;
    }
    .kt-cms-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #eff6ff;
        color: #1d4ed8;
        font-size: 12px;
        font-weight: 700;
        border: 1px solid #bfdbfe;
    }
    .kt-cms-tabs {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-bottom: 16px;
    }
    .kt-cms-tabs .btn {
        border-radius: 999px;
        padding-left: 14px;
        padding-right: 14px;
    }
    .kt-cms-sidebar {
        background: #fff;
        border: 1px solid #dde7f1;
        border-radius: 18px;
        padding: 16px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        min-width: 0;
    }
    .kt-cms-muted {
        color: #64748b;
        font-size: 13px;
    }
    .kt-cms-divider {
        height: 1px;
        background: linear-gradient(90deg, transparent, #e2e8f0 20%, #e2e8f0 80%, transparent);
        margin: 16px 0;
    }
    .kt-cms-soft-table {
        border: 1px solid #dde7f1;
        border-radius: 16px;
        overflow: hidden;
    }
    .kt-cms-soft-table .table { margin-bottom: 0; }
    .kt-cms-soft-table .table > thead > tr > th {
        background: #f8fbff;
        border-bottom: 1px solid #dde7f1;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: #475569;
    }
    .kt-cms-soft-table .table > tbody > tr > td { vertical-align: top; }
    .kt-cms-hero .btn,
    .kt-cms-card .btn,
    .kt-cms-sidebar .btn { border-radius: 10px; }
    .kt-cms-card-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 14px;
    }
    .kt-cms-stat-card {
        background: linear-gradient(180deg,#ffffff 0%,#f8fbff 100%);
        border: 1px solid #dbe7f3;
        border-radius: 16px;
        padding: 14px 16px;
        min-width: 0;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }
    .kt-cms-stat-card .label {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        padding: 5px 10px;
        font-size: 11px;
        font-weight: 700;
    }
    .kt-cms-stat-card strong {
        display: block;
        margin-top: 8px;
        font-size: 24px;
        line-height: 1.1;
        color: #0f172a;
    }
    .kt-cms-asset-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .kt-cms-asset-card {
        background: #fff;
        border: 1px solid #dde7f1;
        border-radius: 16px;
        padding: 14px;
        min-width: 0;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
    }
    .kt-cms-asset-thumb {
        aspect-ratio: 16/10;
        border-radius: 12px;
        background: #f8fbff;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin-bottom: 12px;
    }
    .kt-cms-asset-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }
    .kt-cms-asset-meta .kt-cms-pill {
        background: #f8fbff;
        border-color: #dbe7f3;
        color: #334155;
    }
    .kt-cms-app-card {
        background: #fff;
        border: 1px solid #dde7f1;
        border-radius: 18px;
        padding: 16px;
        min-width: 0;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        height: 100%;
    }
    .kt-cms-app-head {
        display: flex;
        gap: 12px;
        align-items: flex-start;
    }
    .kt-cms-app-icon {
        width: 44px;
        height: 44px;
        border-radius: 14px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eff6ff;
        color: #1d4ed8;
        font-weight: 800;
        flex: 0 0 auto;
    }
    .kt-cms-app-body {
        margin-top: 12px;
    }
    .kt-cms-workspace-grid {
        display: grid;
        grid-template-columns: repeat(12, minmax(0, 1fr));
        gap: 14px;
    }
    .kt-cms-page-card,
    .kt-cms-section-card,
    .kt-cms-editor-card,
    .kt-cms-preview-card {
        background: #fff;
        border: 1px solid #dde7f1;
        border-radius: 18px;
        padding: 16px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        min-width: 0;
    }
    .kt-cms-page-card {
        margin-bottom: 12px;
    }
    .kt-cms-page-card__head,
    .kt-cms-section-card__head,
    .kt-cms-editor-card__head,
    .kt-cms-preview-card__head {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
    }
    .kt-cms-page-card__title,
    .kt-cms-section-card__title,
    .kt-cms-app-name {
        font-size: 16px;
        font-weight: 800;
        color: #0f172a;
        margin: 0;
        word-break: break-word;
    }
    .kt-cms-section-card__canvas {
        background: linear-gradient(180deg, #fbfdff, #f7fbff);
        border: 1px solid #dbe7f3;
        border-radius: 16px;
        padding: 14px;
        margin: 12px 0;
    }
    .kt-cms-section-card__meta,
    .kt-cms-page-card__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }
    .kt-cms-section-card__actions,
    .kt-cms-page-card__actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 12px;
    }
    .kt-cms-media-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 14px;
    }
    .kt-cms-media-card {
        background: #fff;
        border: 1px solid #dde7f1;
        border-radius: 18px;
        padding: 14px;
        box-shadow: 0 10px 24px rgba(15, 23, 42, .04);
        min-width: 0;
    }
    .kt-cms-media-thumb {
        aspect-ratio: 16 / 10;
        border-radius: 14px;
        background: #f8fbff;
        border: 1px solid #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        margin-bottom: 12px;
    }
    .kt-cms-media-card__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-top: 10px;
    }
    .kt-cms-pricing-locked {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .kt-cms-pricing-locked .kt-cms-stat-card strong {
        font-size: 20px;
    }
    @media (max-width: 1199px) {
        .kt-cms-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .kt-cms-grid { grid-template-columns: 1fr; }
        .kt-cms-card-grid,
        .kt-cms-asset-grid,
        .kt-cms-media-grid,
        .kt-cms-pricing-locked { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
        .kt-cms-kpis { grid-template-columns: 1fr; }
        .kt-cms-tabs { gap: 6px; }
        .kt-cms-tabs .btn { width: 100%; }
        .kt-cms-page-card__head,
        .kt-cms-section-card__head,
        .kt-cms-editor-card__head,
        .kt-cms-preview-card__head { flex-direction: column; }
    }
</style>
