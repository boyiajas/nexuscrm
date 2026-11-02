function t(e){return e==null?"":String(e).replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#39;")}function E(e){return`"${String(e??"").replace(/"/g,'""')}"`}function R(e){return String(e||"Report").trim().replace(/[^a-zA-Z0-9_\-.]/g,"_")}function $(e,c){const a=URL.createObjectURL(e),i=document.createElement("a");i.href=a,i.download=R(c),document.body.appendChild(i),i.click(),i.remove(),setTimeout(()=>URL.revokeObjectURL(a),1e3)}function S(e,c){const a=e.endsWith(".csv")?e:`${e}.csv`,i="\uFEFF"+c.map(b=>Array.isArray(b)?b.map(E).join(","):"").join(`\r
`),f=new Blob([i],{type:"text/csv;charset=utf-8;"});$(f,a)}function j(e,c){const a=e.endsWith(".xls")?e:`${e}.xls`,{title:i="EXECUTIVE REPORT",systemName:f="SR Solution",metadata:b=[],summaryKpis:p=[],tableHeaders:m=[],tableRows:v=[],totalsRow:x=null}=c,l=Math.max(m.length,6);let h="";b.length>0&&(h=b.map(o=>`
      <tr>
        <td class="meta-label" style="font-weight: bold; color: #475569; padding: 4px 8px; border: none;">${t(o.label)}:</td>
        <td class="meta-value" colspan="${l-1}" style="color: #0f172a; padding: 4px 8px; border: none;">${t(o.value)}</td>
      </tr>
    `).join(""));let g="";if(p.length>0){g=`
      <tr><td colspan="${l}" style="height: 12px; border: none;"></td></tr>
      <tr><td colspan="${l}" class="section-hdr" style="background-color: #0284c7; color: #ffffff; font-weight: bold; font-size: 11pt; padding: 6px 10px;">EXECUTIVE SUMMARY &amp; METRICS</td></tr>
    `;for(let o=0;o<p.length;o+=2){const d=p[o],s=p[o+1];g+=`
        <tr>
          <td style="font-weight: bold; background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 5px 8px;">${t(d.label)}</td>
          <td style="font-weight: bold; color: #0369a1; border: 1px solid #cbd5e1; padding: 5px 8px;">${t(d.value)}</td>
          ${s?`
            <td style="font-weight: bold; background-color: #f8fafc; border: 1px solid #cbd5e1; padding: 5px 8px;">${t(s.label)}</td>
            <td colspan="${l-3}" style="font-weight: bold; color: #0369a1; border: 1px solid #cbd5e1; padding: 5px 8px;">${t(s.value)}</td>
          `:`<td colspan="${l-2}" style="border: 1px solid #cbd5e1;"></td>`}
        </tr>
      `}}const u=m.map(o=>`
    <th style="background-color: #0f172a; color: #ffffff; font-weight: bold; font-size: 9.5pt; padding: 8px 10px; border: 1px solid #334155; text-align: left;">
      ${t(o)}
    </th>
  `).join(""),w=v.map((o,d)=>{const s=d%2===0?"#ffffff":"#f8fafc";return`<tr>${o.map(z=>`
      <td style="background-color: ${s}; border: 1px solid #cbd5e1; padding: 5px 8px; font-size: 9pt; mso-number-format: '\\@';">
        ${t(z)}
      </td>
    `).join("")}</tr>`}).join("");let n="";x&&x.length>0&&(n=`<tr class="totals-row">${x.map((d,s)=>`
      <td style="background-color: #e2e8f0; font-weight: bold; font-size: 9.5pt; border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 6px 8px; ${s===0?"text-transform: uppercase;":""}">
        ${t(d)}
      </td>
    `).join("")}</tr>`);const y=`
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
          <td colspan="${l}" style="font-size: 15pt; font-weight: bold; color: #0f172a; padding: 10px 0; border: none;">
            ${t(f)} &mdash; ${t(i)}
          </td>
        </tr>
        ${h}
        ${g}
        <tr><td colspan="${l}" style="height: 14px; border: none;"></td></tr>
        <tr><td colspan="${l}" style="background-color: #0f172a; color: #ffffff; font-weight: bold; font-size: 11pt; padding: 6px 10px;">DETAILED PERFORMANCE MATRIX</td></tr>
        <tr>${u}</tr>
        ${w}
        ${n}
      </table>
    </body>
    </html>
  `,r=new Blob([y],{type:"application/vnd.ms-excel;charset=utf-8;"});$(r,a)}function C(e,c){const a=e.endsWith(".doc")?e:`${e}.doc`,{title:i="EXECUTIVE REPORT",systemName:f="SR Solution",metadata:b=[],summaryKpis:p=[],tableHeaders:m=[],tableRows:v=[],totalsRow:x=null}=c;let l="";b.length>0&&(l=`
      <table class="meta-table" style="width: 100%; border-collapse: collapse; margin-bottom: 16px;">
        ${b.map(r=>`
          <tr>
            <td style="width: 180px; font-weight: bold; color: #475569; padding: 4px 6px; font-size: 9.5pt; border: none;">${t(r.label)}:</td>
            <td style="color: #0f172a; padding: 4px 6px; font-size: 9.5pt; border: none;">${t(r.value)}</td>
          </tr>
        `).join("")}
      </table>
    `);let h="";if(p.length>0){let r="";for(let o=0;o<p.length;o+=2){const d=p[o],s=p[o+1];r+=`
        <tr>
          <td style="background-color: #f8fafc; font-weight: 600; padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 9pt;">${t(d.label)}</td>
          <td style="font-weight: bold; color: #0284c7; padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 9.5pt;">${t(d.value)}</td>
          ${s?`
            <td style="background-color: #f8fafc; font-weight: 600; padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 9pt;">${t(s.label)}</td>
            <td style="font-weight: bold; color: #0284c7; padding: 6px 10px; border: 1px solid #cbd5e1; font-size: 9.5pt;">${t(s.value)}</td>
          `:'<td colspan="2" style="border: 1px solid #cbd5e1;"></td>'}
        </tr>
      `}h=`
      <h3 style="font-size: 11pt; color: #0f172a; border-left: 4px solid #0284c7; padding-left: 8px; margin: 16px 0 8px 0; text-transform: uppercase;">Executive Summary &amp; Key Metrics</h3>
      <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        ${r}
      </table>
    `}const g=m.map(r=>`
    <th style="background-color: #0f172a; color: #ffffff; font-weight: bold; font-size: 8.5pt; padding: 6px 5px; border: 1px solid #334155; text-align: left;">
      ${t(r)}
    </th>
  `).join(""),u=v.map((r,o)=>{const d=o%2===0?"#ffffff":"#f8fafc";return`<tr>${r.map(k=>`
      <td style="background-color: ${d}; border: 1px solid #cbd5e1; padding: 4px 5px; font-size: 8pt;">
        ${t(k)}
      </td>
    `).join("")}</tr>`}).join("");let w="";x&&x.length>0&&(w=`<tr>${x.map(o=>`
      <td style="background-color: #e2e8f0; font-weight: bold; font-size: 8.5pt; border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 5px; color: #0f172a;">
        ${t(o)}
      </td>
    `).join("")}</tr>`);const n=`
    <html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:w='urn:schemas-microsoft-com:office:word' xmlns='http://www.w3.org/TR/REC-html40'>
    <head>
      <meta charset='utf-8'>
      <title>${t(f)} - ${t(i)}</title>
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
          <div class="sys-name">${t(f)}</div>
          <div class="doc-title">${t(i)}</div>
        </div>

        ${l}
        ${h}

        <h3 style="font-size: 11pt; color: #0f172a; border-left: 4px solid #0f172a; padding-left: 8px; margin: 18px 0 8px 0; text-transform: uppercase;">Itemized Detailed Breakdown</h3>
        <table style="width: 100%; border-collapse: collapse; margin-top: 6px;">
          <thead><tr>${g}</tr></thead>
          <tbody>
            ${u}
            ${w}
          </tbody>
        </table>

        <div class="footer-note">
          CONFIDENTIAL &bull; Generated automatically by ${t(f)} &bull; ${t(new Date().toLocaleString())}
        </div>
      </div>
    </body>
    </html>
  `,y=new Blob([n],{type:"application/msword;charset=utf-8;"});$(y,a)}function H(e){const{title:c="EXECUTIVE REPORT",systemName:a="SR Solution",metadata:i=[],summaryKpis:f=[],tableHeaders:b=[],tableRows:p=[],totalsRow:m=null}=e,v=i.map(n=>`
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 6px 12px;">
      <div style="font-size: 10px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">${t(n.label)}</div>
      <div style="font-size: 12px; font-weight: 600; color: #0f172a; margin-top: 2px;">${t(n.value)}</div>
    </div>
  `).join(""),x=f.map(n=>`
    <div style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; min-width: 120px;">
      <div style="font-size: 10px; color: #64748b; font-weight: 600; text-transform: uppercase;">${t(n.label)}</div>
      <div style="font-size: 15px; font-weight: 800; color: #0284c7; margin-top: 2px;">${t(n.value)}</div>
    </div>
  `).join(""),l=b.map(n=>`
    <th style="background-color: #0f172a; color: #ffffff; font-weight: 700; font-size: 9px; padding: 6px 6px; border: 1px solid #334155; text-align: left; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap;">
      ${t(n)}
    </th>
  `).join(""),h=p.map((n,y)=>{const r=y%2===0?"#ffffff":"#f8fafc";return`<tr>${n.map(d=>`
      <td style="background-color: ${r}; border: 1px solid #cbd5e1; padding: 4px 6px; font-size: 8.5px; color: #1e293b; white-space: nowrap;">
        ${t(d)}
      </td>
    `).join("")}</tr>`}).join("");let g="";m&&m.length>0&&(g=`<tr>${m.map((y,r)=>`
      <td style="background-color: #e2e8f0; font-weight: 800; font-size: 9px; border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; border-left: 1px solid #cbd5e1; border-right: 1px solid #cbd5e1; padding: 5px 6px; color: #0f172a; white-space: nowrap; ${r===0?"text-transform: uppercase;":""}">
        ${t(y)}
      </td>
    `).join("")}</tr>`);const u=window.open("","_blank","width=1200,height=800,menubar=no,toolbar=no,location=no,status=no");if(!u){alert("Popup was blocked by your browser. Please allow popups to generate and view the PDF report.");return}const w=`
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <title>${t(a)} - ${t(c)}</title>
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
          <strong style="font-size: 13px;">${t(a)} &mdash; PDF Print Preview</strong>
          <span style="font-size: 11px; opacity: 0.8; margin-left: 10px;">Select &quot;Save as PDF&quot; in the destination dropdown to export.</span>
        </div>
        <div>
          <button class="btn-print" onclick="window.print()">&#128438; Print / Save as PDF</button>
          <button class="btn-close" onclick="window.close()">&#10005; Close</button>
        </div>
      </div>

      <div class="header-container">
        <div>
          <span class="system-badge">${t(a)}</span>
          <h1 class="report-title">${t(c)}</h1>
          <div class="report-sub">Official Management &amp; Financial Telemetry Statement</div>
        </div>
        <div style="text-align: right; font-size: 9px; color: #64748b;">
          <div><strong>Generated:</strong> ${t(new Date().toLocaleString())}</div>
          <div><strong>Classification:</strong> Confidential / Executive Review</div>
        </div>
      </div>

      <div class="meta-grid">
        ${v}
      </div>

      ${f.length>0?`
        <div class="section-header">Executive Summary &amp; Key Performance Indicators</div>
        <div class="kpi-grid">
          ${x}
        </div>
      `:""}

      <div class="section-header">Detailed Performance Matrix Breakdown</div>
      <div class="table-wrapper">
        <table>
          <thead><tr>${l}</tr></thead>
          <tbody>
            ${h}
            ${g}
          </tbody>
        </table>
      </div>

      <div class="footer-bar">
        <div>${t(a)} &bull; Automated Enterprise Reporting Service</div>
        <div>Document ID: REP-${Date.now().toString(36).toUpperCase()} &bull; Page 1 of 1</div>
      </div>

      <script>
        // Auto trigger print dialogue after rendering
        window.addEventListener('load', function() {
          setTimeout(function() {
            window.print();
          }, 400);
        });
      <\/script>
    </body>
    </html>
  `;u.document.open(),u.document.write(w),u.document.close()}export{j as a,C as b,S as c,H as e};
