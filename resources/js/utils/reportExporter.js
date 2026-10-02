/**
 * Universal Report Exporter Utility
 * Supports PDF (Printable Vector Document), Microsoft Excel (.xls),
 * Microsoft Word (.doc), and RFC-4180 CSV (.csv).
 */

function escapeHtml(value) {
  if (value === null || value === undefined) return '';
  return String(value)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function escapeCsv(value) {
  return `"${String(value ?? '').replace(/"/g, '""')}"`;
}

function sanitizeFilename(name) {
  return String(name || 'Report').trim().replace(/[^a-zA-Z0-9_\-.]/g, '_');
}

/**
 * Download a client-generated Blob as a file
 */
export function triggerBlobDownload(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = sanitizeFilename(filename);
  document.body.appendChild(link);
  link.click();
  link.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/**
 * 1. Export as Standard RFC-4180 CSV
 */
export function exportToCsv(filename, rows) {
  const finalFilename = filename.endsWith('.csv') ? filename : `${filename}.csv`;
  const csvContent = '\uFEFF' + rows
    .map((row) => (Array.isArray(row) ? row.map(escapeCsv).join(',') : ''))
    .join('\r\n');

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  triggerBlobDownload(blob, finalFilename);
}

/**
 * 2. Export as Microsoft Excel (.xls)
 * Uses native Office XML/HTML Spreadsheet format with custom styling and cell types.
 */
export function exportToExcel(filename, reportData) {
  const finalFilename = filename.endsWith('.xls') ? filename : `${filename}.xls`;
  const {
    title = 'EXECUTIVE REPORT',
    systemName = 'SR Solution',
    metadata = [],
    summaryKpis = [],
    tableHeaders = [],
    tableRows = [],
    totalsRow = null,
  } = reportData;

  const colSpan = Math.max(tableHeaders.length, 6);

  // Metadata rows
  let metaRowsHtml = '';
  if (metadata.length > 0) {
    metaRowsHtml = metadata.map((m) => `
      <tr>
        <td class="meta-label" style="font-weight: bold; color: #475569; padding: 4px 8px; border: none;">${escapeHtml(m.label)}:</td>
        <td class="meta-value" colspan="${colSpan - 1}" style="color: #0f172a; padding: 4px 8px; border: none;">${escapeHtml(m.value)}</td>
      </tr>
    `).join('');
  }

  // Summary KPIs rows
  let summaryRowsHtml = '';
  if (summaryKpis.length > 0) {
    summaryRowsHtml = `
      <tr><td colspan="${colSpan}" style="height: 12px; border: none;"></td></tr>
      <tr><td colspan="${colSpan}" class="section-hdr" style="background-color: #0284c7; color: #ffffff; font-weight: bold; font-size: 11pt; padding: 6px 10px;">EXECUTIVE SUMMARY &amp; METRICS</td></tr>
    `;

    // Render KPIs in pairs of (label, value) across columns
    for (let i = 0; i < summaryKpis.length; i += 2) {
      const k1 = summaryKpis[i];
      const k2 = summaryKpis[i + 1];
      summaryRowsHtml += `
        <tr>
          <td style="font-weight: bold; background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 5px 8px;">${escapeHtml(k1.label)}</td>
          <td style="font-weight: bold; color: #0369a1; border: 1px solid #cbd5e1; padding: 5px 8px;">${escapeHtml(k1.value)}</td>
          ${k2 ? `
            <td style="font-weight: bold; background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 5px 8px;">${escapeHtml(k2.label)}</td>
            <td colspan="${colSpan - 3}" style="font-weight: bold; color: #0369a1; border: 1px solid #cbd5e1; padding: 5px 8px;">${escapeHtml(k2.value)}</td>
          ` : `<td colspan="${colSpan - 2}" style="border: 1px solid #cbd5e1;"></td>`}
        </tr>
      `;
    }
  }

  // Data table headers
  const thHtml = tableHeaders.map((h) => `
    <th style="background-color: #0f172a; color: #ffffff; font-weight: bold; font-size: 9.5pt; padding: 8px 10px; border: 1px solid #334155; text-align: left;">
      ${escapeHtml(h)}
    </th>
  `).join('');

  // Data rows
  const tdRowsHtml = tableRows.map((row, idx) => {
    const bgColor = idx % 2 === 0 ? '#ffffff' : '#f8fafc';
    const cells = row.map((cell) => `
      <td style="background-color: ${bgColor}; border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 9pt; mso-number-format: '\\@';">
        ${escapeHtml(cell)}
      </td>
    `).join('');
    return `<tr>${cells}</tr>`;
  }).join('');

  // Totals row
  let totalsHtml = '';
  if (totalsRow && totalsRow.length > 0) {
    const cells = totalsRow.map((cell, idx) => `
      <td style="background-color: #e2e8f0; font-weight: bold; font-size: 9.5pt; border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 6px 8px; ${idx === 0 ? 'text-transform: uppercase;' : ''}">
        ${escapeHtml(cell)}
      </td>
    `).join('');
    totalsHtml = `<tr class="totals-row">${cells}</tr>`;
  }

  const excelHtml = `
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
      <meta http-equiv="content-type" content="application/vnd.ms-excel; charset=UTF-8">
      <!--[if gte mso 9]>
      <xml>
        <x:ExcelWorkbook>
          <x:ExcelWorksheets>
            <x:ExcelWorksheet>
              <x:Name>Statement</x:Name>
              <x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions>
            </x:ExcelWorksheet>
          </x:ExcelWorksheets>
        </x:ExcelWorkbook>
      </xml>
      <![endif]-->
      <style>
        body { font-family: 'Segoe UI', Calibri, Arial, sans-serif; }
      </style>
    </head>
    <body>
      <table>
        <tr>
          <td colspan="${colSpan}" style="font-size: 15pt; font-weight: bold; color: #0f172a; padding: 10px 0; border: none;">
            ${escapeHtml(systemName)} &mdash; ${escapeHtml(title)}
          </td>
        </tr>
        ${metaRowsHtml}
        ${summaryRowsHtml}
        <tr><td colspan="${colSpan}" style="height: 14px; border: none;"></td></tr>
        <tr><td colspan="${colSpan}" style="background-color: #0f172a; color: #ffffff; font-weight: bold; font-size: 11pt; padding: 6px 10px;">DETAILED PERFORMANCE MATRIX</td></tr>
        <tr>${thHtml}</tr>
        ${tdRowsHtml}
        ${totalsHtml}
      </table>
    </body>
    </html>
  `;

  const blob = new Blob([excelHtml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
  triggerBlobDownload(blob, finalFilename);
}

/**
 * 3. Export as Microsoft Word (.doc)
 * Uses native Office Word HTML document format with landscape orientation and styled layouts.
 */
export function exportToWord(filename, reportData) {
  const finalFilename = filename.endsWith('.doc') ? filename : `${filename}.doc`;
  const {
    title = 'EXECUTIVE REPORT',
    systemName = 'SR Solution',
    metadata = [],
    summaryKpis = [],
    tableHeaders = [],
    tableRows = [],
    totalsRow = null,
  } = reportData;

  // Metadata table
  let metaHtml = '';
  if (metadata.length > 0) {
    metaHtml = `
      <table class="meta-table" style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
        ${metadata.map((m) => `
          <tr>
            <td style="width: 180px; font-weight: bold; color: #475569; padding: 4px 6px; font-size: 9.5pt; border: none;">${escapeHtml(m.label)}:</td>
            <td style="color: #0f172a; padding: 4px 6px; font-size: 9.5pt; border: none;">${escapeHtml(m.value)}</td>
          </tr>
        `).join('')}
      </table>
    `;
  }

  // Summary KPIs table
  let summaryHtml = '';
  if (summaryKpis.length > 0) {
    let cellsHtml = '';
    for (let i = 0; i < summaryKpis.length; i += 2) {
      const k1 = summaryKpis[i];
      const k2 = summaryKpis[i + 1];
      cellsHtml += `
        <tr>
          <td style="background-color: #f8fafc; font-weight: 600; padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 9pt;">${escapeHtml(k1.label)}</td>
          <td style="font-weight: bold; color: #0284c7; padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 9.5pt;">${escapeHtml(k1.value)}</td>
          ${k2 ? `
            <td style="background-color: #f8fafc; font-weight: 600; padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 9pt;">${escapeHtml(k2.label)}</td>
            <td style="font-weight: bold; color: #0284c7; padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 9.5pt;">${escapeHtml(k2.value)}</td>
          ` : `<td colspan="2" style="border: 1px solid #cbd5e1;"></td>`}
        </tr>
      `;
    }

    summaryHtml = `
      <h3 style="font-size: 11pt; color: #0f172a; border-left: 4px solid #0284c7; padding-left: 8px; margin: 16px 0 8px 0; text-transform: uppercase;">Executive Summary &amp; Key Metrics</h3>
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        ${cellsHtml}
      </table>
    `;
  }

  // Data table
  const thHtml = tableHeaders.map((h) => `
    <th style="background-color: #0f172a; color: #ffffff; font-weight: bold; font-size: 8.5pt; padding: 6px 5px; border: 1px solid #334155; text-align: left;">
      ${escapeHtml(h)}
    </th>
  `).join('');

  const tdRowsHtml = tableRows.map((row, idx) => {
    const bgColor = idx % 2 === 0 ? '#ffffff' : '#f8fafc';
    const cells = row.map((cell) => `
      <td style="background-color: ${bgColor}; border: 1px solid #cbd5e1; padding: 4px 5px; font-size: 8pt;">
        ${escapeHtml(cell)}
      </td>
    `).join('');
    return `<tr>${cells}</tr>`;
  }).join('');

  let totalsHtml = '';
  if (totalsRow && totalsRow.length > 0) {
    const cells = totalsRow.map((cell) => `
      <td style="background-color: #e2e8f0; font-weight: bold; font-size: 8.5pt; border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 5px; color: #0f172a;">
        ${escapeHtml(cell)}
      </td>
    `).join('');
    totalsHtml = `<tr>${cells}</tr>`;
  }

  const wordHtml = `
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
      <meta charset='utf-8'>
      <title>${escapeHtml(systemName)} - ${escapeHtml(title)}</title>
      <style>
        @page Section1 {
          size: 11.0in 8.5in;
          mso-page-orientation: landscape;
          margin: 0.5in 0.5in 0.5in 0.5in;
        }
        div.Section1 { page: Section1; }
        body {
          font-family: Calibri, 'Segoe UI', Arial, sans-serif;
          color: #1e293b;
          font-size: 9.5pt;
        }
        .header-band {
          border-bottom: 3px solid #0284c7;
          padding-bottom: 8px;
          margin-bottom: 14px;
        }
        .sys-name {
          font-size: 16pt;
          font-weight: bold;
          color: #0f172a;
          margin: 0;
        }
        .doc-title {
          font-size: 12pt;
          font-weight: 600;
          color: #0284c7;
          margin: 2px 0 0 0;
        }
        .footer-note {
          margin-top: 20px;
          font-size: 8pt;
          color: #64748b;
          border-top: 1px solid #e2e8f0;
          padding-top: 8px;
        }
      </style>
    </head>
    <body>
      <div class="Section1">
        <div class="header-band">
          <div class="sys-name">${escapeHtml(systemName)}</div>
          <div class="doc-title">${escapeHtml(title)}</div>
        </div>

        ${metaHtml}
        ${summaryHtml}

        <h3 style="font-size: 11pt; color: #0f172a; border-left: 4px solid #0f172a; padding-left: 8px; margin: 18px 0 8px 0; text-transform: uppercase;">Itemized Detailed Breakdown</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
          <thead><tr>${thHtml}</tr></thead>
          <tbody>
            ${tdRowsHtml}
            ${totalsHtml}
          </tbody>
        </table>

        <div class="footer-note">
          CONFIDENTIAL &bull; Generated automatically by ${escapeHtml(systemName)} &bull; ${escapeHtml(new Date().toLocaleString())}
        </div>
      </div>
    </body>
    </html>
  `;

  const blob = new Blob([wordHtml], { type: 'application/msword;charset=utf-8;' });
  triggerBlobDownload(blob, finalFilename);
}

/**
 * 4. Export as Printable Vector PDF
 * Opens a dedicated executive print dialog window with vector fidelity and landscape styling.
 */
export function exportToPdf(reportData) {
  const {
    title = 'EXECUTIVE REPORT',
    systemName = 'SR Solution',
    metadata = [],
    summaryKpis = [],
    tableHeaders = [],
    tableRows = [],
    totalsRow = null,
  } = reportData;

  // Metadata cards
  const metaHtml = metadata.map((m) => `
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 12px;">
      <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">${escapeHtml(m.label)}</div>
      <div style="font-size: 12px; font-weight: 600; color: #0f172a; margin-top: 2px;">${escapeHtml(m.value)}</div>
    </div>
  `).join('');

  // KPI cards
  const kpiCardsHtml = summaryKpis.map((k) => `
    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; min-width: 120px;">
      <div style="font-size: 10px; color: #64748b; font-weight: 600; text-transform: uppercase;">${escapeHtml(k.label)}</div>
      <div style="font-size: 15px; font-weight: 800; color: #0284c7; margin-top: 2px;">${escapeHtml(k.value)}</div>
    </div>
  `).join('');

  // Table headers
  const thHtml = tableHeaders.map((h) => `
    <th style="background-color: #0f172a; color: #ffffff; font-weight: 700; font-size: 9px; padding: 6px 6px; border: 1px solid #334155; text-align: left; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap;">
      ${escapeHtml(h)}
    </th>
  `).join('');

  // Table rows
  const trHtml = tableRows.map((row, idx) => {
    const bgColor = idx % 2 === 0 ? '#ffffff' : '#f8fafc';
    const cells = row.map((cell) => `
      <td style="background-color: ${bgColor}; border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 8.5px; color: #1e293b; white-space: nowrap;">
        ${escapeHtml(cell)}
      </td>
    `).join('');
    return `<tr>${cells}</tr>`;
  }).join('');

  // Grand totals
  let totalsHtml = '';
  if (totalsRow && totalsRow.length > 0) {
    const cells = totalsRow.map((cell, idx) => `
      <td style="background-color: #e2e8f0; font-weight: 800; font-size: 9px; border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 5px 6px; color: #0f172a; white-space: nowrap; ${idx === 0 ? 'text-transform: uppercase;' : ''}">
        ${escapeHtml(cell)}
      </td>
    `).join('');
    totalsHtml = `<tr>${cells}</tr>`;
  }

  const printWindow = window.open('', '_blank', 'width=1200,height=800,menubar=no,toolbar=no,location=no,status=no');
  if (!printWindow) {
    alert('Popup was blocked by your browser. Please allow popups to generate and view the PDF report.');
    return;
  }

  const printDocumentHtml = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>${escapeHtml(systemName)} - ${escapeHtml(title)}</title>
      <style>
        @page {
          size: A4 landscape;
          margin: 8mm 8mm 8mm 8mm;
        }
        * {
          box-sizing: border-box;
          -webkit-print-color-adjust: exact !important;
          print-color-adjust: exact !important;
        }
        body {
          font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
          color: #0f172a;
          background: #ffffff;
          margin: 0;
          padding: 12px;
          font-size: 10px;
        }
        .no-print-bar {
          background: #1e293b;
          color: #ffffff;
          padding: 10px 16px;
          border-radius: 8px;
          margin-bottom: 16px;
          display: flex;
          justify-content: space-between;
          align-items: center;
          box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .btn-print {
          background: #0284c7;
          color: #ffffff;
          border: none;
          border-radius: 5px;
          padding: 8px 16px;
          font-weight: 600;
          font-size: 12px;
          cursor: pointer;
        }
        .btn-print:hover { background: #0369a1; }
        .btn-close {
          background: #475569;
          color: #ffffff;
          border: none;
          border-radius: 5px;
          padding: 8px 14px;
          font-weight: 600;
          font-size: 12px;
          cursor: pointer;
          margin-left: 8px;
        }
        .header-container {
          border-bottom: 3px solid #0284c7;
          padding-bottom: 8px;
          margin-bottom: 12px;
          display: flex;
          justify-content: space-between;
          align-items: flex-end;
        }
        .system-badge {
          display: inline-block;
          background: #0284c7;
          color: #ffffff;
          font-size: 9px;
          font-weight: 800;
          padding: 2px 8px;
          border-radius: 4px;
          text-transform: uppercase;
          letter-spacing: 0.8px;
          margin-bottom: 4px;
        }
        .report-title {
          font-size: 18px;
          font-weight: 800;
          color: #0f172a;
          margin: 0;
          letter-spacing: -0.3px;
        }
        .report-sub {
          font-size: 10px;
          color: #64748b;
          margin-top: 2px;
        }
        .meta-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
          gap: 8px;
          margin-bottom: 14px;
        }
        .kpi-grid {
          display: grid;
          grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
          gap: 8px;
          margin-bottom: 16px;
        }
        .section-header {
          font-size: 11px;
          font-weight: 800;
          color: #0f172a;
          text-transform: uppercase;
          letter-spacing: 0.5px;
          margin: 12px 0 6px 0;
          display: flex;
          align-items: center;
          gap: 6px;
        }
        .section-header::before {
          content: '';
          display: inline-block;
          width: 4px;
          height: 12px;
          background: #0284c7;
          border-radius: 2px;
        }
        .table-wrapper {
          width: 100%;
          overflow-x: auto;
          margin-bottom: 16px;
        }
        table {
          width: 100%;
          border-collapse: collapse;
          font-size: 8.5px;
        }
        .footer-bar {
          margin-top: 16px;
          padding-top: 8px;
          border-top: 1px solid #cbd5e1;
          display: flex;
          justify-content: space-between;
          font-size: 8px;
          color: #64748b;
        }
        @media print {
          .no-print-bar { display: none !important; }
          body { padding: 0 !important; }
        }
      </style>
    </head>
    <body>
      <div class="no-print-bar">
        <div>
          <strong style="font-size: 13px;">${escapeHtml(systemName)} &mdash; PDF Print Preview</strong>
          <span style="font-size: 11px; opacity: 0.8; margin-left: 10px;">Select &quot;Save as PDF&quot; in the destination dropdown to export.</span>
        </div>
        <div>
          <button class="btn-print" onclick="window.print()">&#128438; Print / Save as PDF</button>
          <button class="btn-close" onclick="window.close()">&#10005; Close</button>
        </div>
      </div>

      <div class="header-container">
        <div>
          <span class="system-badge">${escapeHtml(systemName)}</span>
          <h1 class="report-title">${escapeHtml(title)}</h1>
          <div class="report-sub">Official Management &amp; Financial Telemetry Statement</div>
        </div>
        <div style="text-align: right; font-size: 9px; color: #64748b;">
          <div><strong>Generated:</strong> ${escapeHtml(new Date().toLocaleString())}</div>
          <div><strong>Classification:</strong> Confidential / Executive Review</div>
        </div>
      </div>

      <div class="meta-grid">
        ${metaHtml}
      </div>

      ${summaryKpis.length > 0 ? `
        <div class="section-header">Executive Summary &amp; Key Performance Indicators</div>
        <div class="kpi-grid">
          ${kpiCardsHtml}
        </div>
      ` : ''}

      <div class="section-header">Detailed Performance Matrix Breakdown</div>
      <div class="table-wrapper">
        <table>
          <thead><tr>${thHtml}</tr></thead>
          <tbody>
            ${trHtml}
            ${totalsHtml}
          </tbody>
        </table>
      </div>

      <div class="footer-bar">
        <div>${escapeHtml(systemName)} &bull; Automated Enterprise Reporting Service</div>
        <div>Document ID: REP-${Date.now().toString(36).toUpperCase()} &bull; Page 1 of 1</div>
      </div>

      <script>
        // Auto trigger print dialogue after rendering
        window.addEventListener('load', function() {
          setTimeout(function() {
            window.print();
          }, 400);
        });
      </script>
    </body>
    </html>
  `;

  printWindow.document.open();
  printWindow.document.write(printDocumentHtml);
  printWindow.document.close();
}
