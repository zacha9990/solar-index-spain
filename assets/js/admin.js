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

    // ── Backfill Year ────────────────────────────────────────────────────
    var backfillRunning = false;
    var backfillStop    = false;

    $('#sis-backfill-year-btn').on('click', function () {
        if (backfillRunning) { return; }

        var year       = parseInt($('#sis-backfill-year').val(), 10);
        var startMonth = parseInt($('#sis-backfill-start-month').val(), 10);
        var endMonth   = parseInt($('#sis-backfill-end-month').val(), 10);

        if (!year || year < 2021 || startMonth < 1 || endMonth > 12 || startMonth > endMonth) {
            alert('Please enter a valid year (2021+) and month range (1–12).');
            return;
        }

        backfillRunning = true;
        backfillStop    = false;

        var $btn      = $(this);
        var $stopBtn  = $('#sis-backfill-stop-btn');
        var $progress = $('#sis-backfill-progress');

        $btn.prop('disabled', true);
        $stopBtn.show();
        $progress.show().html('<strong>Starting backfill for ' + year + ' (months ' + startMonth + '–' + endMonth + ')…</strong><br>');

        var months  = [];
        for (var m = startMonth; m <= endMonth; m++) { months.push(m); }

        var okCount  = 0;
        var errCount = 0;

        function fetchNext(idx) {
            if (idx >= months.length || backfillStop) {
                backfillRunning = false;
                $btn.prop('disabled', false);
                $stopBtn.hide();

                var summary = backfillStop
                    ? '<span style="color:#c00;">⏹ Stopped.</span>'
                    : '<span style="color:#0a6;">✓ Done.</span>';
                $progress.append('<br><strong>' + summary + ' ' + okCount + ' ok, ' + errCount + ' errors.</strong>');
                $progress.scrollTop($progress[0].scrollHeight);

                // refresh log after backfill
                $.post(ajaxUrl, { action: 'sis_get_log', nonce: nonce })
                    .done(function (res) {
                        if (res.success) { $('#sis-fetch-log').val(res.data.log); }
                    });
                return;
            }

            var month    = months[idx];
            var label    = year + '-' + (month < 10 ? '0' + month : month);
            var $line    = $('<div>Fetching ' + label + '… </div>').appendTo($progress);
            $progress.scrollTop($progress[0].scrollHeight);

            $.post(ajaxUrl, {
                action: 'sis_run_fetch_single',
                nonce:  nonce,
                year:   year,
                month:  month,
            })
            .done(function (res) {
                if (res.success) {
                    okCount++;
                    $line.append('<span style="color:#0a6;">✓ ' + res.data.gwh + ' GWh · ' + res.data.gw + ' GW</span>');
                } else {
                    errCount++;
                    var msg = (res.data && res.data.message) ? res.data.message : String(res.data);
                    $line.append('<span style="color:#c00;">✗ ' + esc(msg) + '</span>');
                }
            })
            .fail(function () {
                errCount++;
                $line.append('<span style="color:#c00;">✗ Request failed</span>');
            })
            .always(function () {
                $progress.scrollTop($progress[0].scrollHeight);
                fetchNext(idx + 1);
            });
        }

        fetchNext(0);
    });

    $('#sis-backfill-stop-btn').on('click', function () {
        backfillStop = true;
        $(this).prop('disabled', true).text('Stopping…');
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
