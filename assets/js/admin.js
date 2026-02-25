/* global sisAdmin, jQuery */
'use strict';

jQuery(function ($) {
    var ajaxUrl = sisAdmin.ajaxUrl;
    var nonce   = sisAdmin.nonce;

    // ── Manual Entry Form ────────────────────────────────────────────────
    $('#sis-manual-form').on('submit', function (e) {
        e.preventDefault();

        var $btn    = $(this).find('[type=submit]');
        var $result = $('#sis-manual-result');

        $btn.prop('disabled', true).text('Saving…');
        $result.html('');

        $.post(ajaxUrl, {
            action:          'sis_manual_entry',
            sis_manual_nonce: $('#sis_manual_nonce').val(),
            year:            $('#sis-year').val(),
            month:           $('#sis-month').val(),
            generation_gwh:  $('#sis-gen').val(),
            capacity_gw:     $('#sis-cap').val(),
            data_source:     $('#sis-source').val(),
        })
        .done(function (res) {
            if (res.success) {
                var m = res.data.metrics;
                var gen = m.gen || {};
                var cap = m.cap || {};
                $result.html(
                    '<div style="color:#0a6;" aria-live="polite">' +
                    '<strong>✓ ' + esc(res.data.message) + '</strong><br>' +
                    'MoM: ' + (gen.mom_pct !== undefined ? gen.mom_pct + '%' : '—') + ' · ' +
                    'YoY: ' + (gen.yoy_pct !== undefined ? gen.yoy_pct + '%' : '—') + ' · ' +
                    'CF: '  + (gen.capacity_factor_pct !== undefined ? gen.capacity_factor_pct + '%' : '—') + '<br>' +
                    'Monthly add: ' + (cap.monthly_addition_gw !== undefined ? cap.monthly_addition_gw + ' GW' : '—') + ' · ' +
                    'Build pace: ' + (cap.build_pace_gw_yr !== undefined ? cap.build_pace_gw_yr + ' GW/yr' : '—') +
                    '</div>'
                );
            } else {
                $result.html('<div style="color:#c00;" aria-live="polite">Error: ' + esc(res.data) + '</div>');
            }
        })
        .fail(function () {
            $result.html('<div style="color:#c00;">Request failed. Check browser console.</div>');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Calculate & Save');
        });
    });

    // ── Run Fetch ────────────────────────────────────────────────────────
    $('#sis-run-fetch').on('click', function () {
        var $btn    = $(this);
        var $status = $('#sis-fetch-status');

        $btn.prop('disabled', true).text('Fetching…');
        $status.html('<em>Connecting to REData…</em>');

        $.post(ajaxUrl, {
            action: 'sis_run_fetch',
            nonce:  nonce,
        })
        .done(function (res) {
            if (res.success) {
                $status.html('<span style="color:#0a6;">✓ Fetch completed.</span>');
                if (res.data && res.data.log) {
                    $('#sis-fetch-log').val(res.data.log);
                }
            } else {
                $status.html('<span style="color:#c00;">Error: ' + esc(res.data) + '</span>');
            }
        })
        .fail(function () {
            $status.html('<span style="color:#c00;">Request failed.</span>');
        })
        .always(function () {
            $btn.prop('disabled', false).text('▶ Run Fetch Now');
        });
    });

    // ── Refresh Log ──────────────────────────────────────────────────────
    $('#sis-refresh-log').on('click', function () {
        $.post(ajaxUrl, { action: 'sis_get_log', nonce: nonce })
        .done(function (res) {
            if (res.success && res.data.log !== undefined) {
                $('#sis-fetch-log').val(res.data.log);
            }
        });
    });

    // ── Regenerate CSV ───────────────────────────────────────────────────
    $('#sis-regen-csv').on('click', function () {
        var $btn    = $(this);
        var $result = $('#sis-csv-result');

        $btn.prop('disabled', true).text('Regenerating…');

        $.post(ajaxUrl, { action: 'sis_regen_csv', nonce: nonce })
        .done(function (res) {
            if (res.success) {
                $result.html('<span style="color:#0a6;">✓ ' + esc(res.data.message) + '</span>');
            } else {
                $result.html('<span style="color:#c00;">Error: ' + esc(res.data) + '</span>');
            }
        })
        .always(function () {
            $btn.prop('disabled', false).text('↻ Regenerate Master CSVs');
        });
    });

    // ── Helpers ──────────────────────────────────────────────────────────
    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
});
