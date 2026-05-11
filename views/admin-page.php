<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap wpse-wrap" id="wpse-app">
    <div class="wpse-header">
        <div class="wpse-header__logo">
            <span class="dashicons dashicons-backup"></span>
            <h1>WP Snapshot Engine</h1>
        </div>
        <div class="wpse-header__actions">
            <p class="wpse-header__sub">Automatic version control for WordPress &amp; Elementor</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="wpse-filters">
        <div class="wpse-filters__group">
            <label for="wpse-filter-type">Type</label>
            <select id="wpse-filter-type">
                <option value="">All types</option>
                <option value="elementor">Elementor</option>
                <option value="post">Post update</option>
                <option value="option">Option change</option>
            </select>
        </div>
        <div class="wpse-filters__group">
            <label for="wpse-filter-date-from">From</label>
            <input type="date" id="wpse-filter-date-from">
        </div>
        <div class="wpse-filters__group">
            <label for="wpse-filter-date-to">To</label>
            <input type="date" id="wpse-filter-date-to">
        </div>
        <button class="button button-secondary" id="wpse-filter-apply">Apply Filters</button>
        <button class="button" id="wpse-filter-reset">Reset</button>
    </div>

    <!-- Timeline -->
    <div class="wpse-layout">
        <div class="wpse-timeline" id="wpse-timeline">
            <div class="wpse-loading" id="wpse-loading">
                <span class="wpse-spinner"></span> Loading snapshots...
            </div>
        </div>

        <!-- Details Panel -->
        <div class="wpse-details" id="wpse-details" hidden>
            <div class="wpse-details__header">
                <h2 id="wpse-details-title">Snapshot Details</h2>
                <button class="wpse-details__close" id="wpse-details-close" aria-label="Close">&times;</button>
            </div>
            <div class="wpse-details__meta" id="wpse-details-meta"></div>
            <div class="wpse-details__tabs">
                <button class="wpse-tab wpse-tab--active" data-tab="payload">Payload</button>
                <button class="wpse-tab" data-tab="diff">Diff vs Current</button>
            </div>
            <div class="wpse-details__body">
                <div class="wpse-tab-panel" id="wpse-tab-payload">
                    <pre class="wpse-json" id="wpse-details-json"></pre>
                </div>
                <div class="wpse-tab-panel" id="wpse-tab-diff" hidden>
                    <div class="wpse-diff" id="wpse-diff-view"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="wpse-pagination" id="wpse-pagination"></div>

    <!-- Toast -->
    <div class="wpse-toast" id="wpse-toast" hidden></div>
</div>
