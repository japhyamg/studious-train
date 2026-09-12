/* P.A.S Screening — Client-side logic */

let currentEntity = 'individual';

function pasSetEntity(radio, type) {
    currentEntity = type;
    document.getElementById('pas-btn-ind').classList.toggle('active', type === 'individual');
    document.getElementById('pas-btn-co').classList.toggle('active', type === 'company');
    document.getElementById('pas-name-fields').classList.toggle('d-none', type === 'company');
    document.getElementById('pas-company-field').classList.toggle('d-none', type === 'individual');
    pasSync();
}

function pasSync() {
    let name = '';
    if (currentEntity === 'individual') {
        const f = document.getElementById('first_name')?.value || '';
        const m = document.getElementById('middle_name')?.value || '';
        const l = document.getElementById('last_name')?.value || '';
        name = [f, m, l].filter(Boolean).join(' ');
    } else {
        name = document.getElementById('company_name')?.value || '';
    }
    document.getElementById('pas-preview-text').textContent = name || 'Enter a name above...';
}

function pasToggleAdv() {
    document.getElementById('pas-adv-panel').classList.toggle('show');
    document.getElementById('pas-adv-chev').classList.toggle('open');
}

function pasSetLoading(on) {
    const btn = document.getElementById('pas-submit-btn');
    btn.disabled = on;
    document.getElementById('pas-spin').style.display = on ? 'block' : 'none';
    document.getElementById('pas-submit-icon').style.display = on ? 'none' : 'inline';
    document.getElementById('pas-submit-text').textContent = on ? 'Screening...' : 'Run Screening';
}

function submitSearch() {
    const firstName = currentEntity === 'individual' ? document.getElementById('first_name')?.value : document.getElementById('company_name')?.value;
    if (!firstName) { alert('Please enter a name.'); return; }
    pasSetLoading(true);

    const fd = new FormData();
    fd.append('first_name', firstName);
    fd.append('middle_name', document.getElementById('middle_name')?.value || '');
    fd.append('last_name', document.getElementById('last_name')?.value || '');
    fd.append('entity_type', currentEntity);
    fd.append('gender', document.getElementById('gender')?.value || '');
    fd.append('date_of_birth', document.getElementById('birthdate')?.value || '');
    fd.append('country', document.getElementById('country')?.value || '');
    fd.append('rc_number', document.getElementById('rc_number')?.value || '');

    fetch(screenUrl, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrfToken }, body: fd })
    .then(r => r.json())
    .then(data => {
        pasSetLoading(false);
        if (data.status === 'success') { resultData = data.data; renderScreeningResult(data.data); }
        else alert(data.message || data.error || 'Screening failed');
    })
    .catch(err => { pasSetLoading(false); alert('Error: ' + err.message); });
}

/* ═══════ RENDER REPORT — matching your PDF layout ═══════ */
function renderScreeningResult(d) {
    document.getElementById('pas-empty').classList.add('d-none');
    document.getElementById('pas-report').classList.remove('d-none');

    const risk = d.risk_level || 'LOW';
    const riskColor = { CRITICAL:'#dc2626', HIGH:'#dc2626', MEDIUM:'#d97706', LOW:'#16a34a' }[risk];
    const riskBg = { CRITICAL:'#fef2f2', HIGH:'#fef2f2', MEDIUM:'#fffbeb', LOW:'#f0fdf4' }[risk];

    const pepMatches = d.pep_matches || [];
    const sanMatches = d.sanction_matches || [];
    const mediaArticles = d.adverse_media_articles || [];

    const pepDetected = d.pep_detected || pepMatches.length > 0;
    const sanDetected = d.sanctions_detected || sanMatches.length > 0;
    const mediaDetected = d.adverse_media_detected || mediaArticles.length > 0;

    // Extract profile data
    const p = d.pep_meta?.data || {};
    const fn = p.found_name || {};
    const nm = p.name_match || {};
    const screened = d.screened_at ? new Date(d.screened_at).toLocaleString() : new Date().toLocaleString();

    const v = (val) => val && val !== 'null' && val !== '' ? val : null;

    let html = `
    <!-- ═══ HEADER ═══ -->
    <div class="pas-rhead p-4 pb-3">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <div class="pas-reyebrow mb-2">P.A.S SCREENING REPORT</div>
                <div class="pas-rname">${d.fullname || d.first_name || '—'}</div>
                <div class="pas-rslug mt-1">${d.slug || ''}</div>
            </div>
            <div class="text-end">
                <div class="pas-rmeta-chip"><i class="bi bi-person me-1"></i>${d.search_context?.entity_type || 'individual'}</div>
                ${d.search_context?.country ? `<div class="pas-rmeta-chip mt-1"><i class="bi bi-globe me-1"></i>${d.search_context.country}</div>` : ''}
            </div>
        </div>
    </div>

    <!-- ═══ IDENTITY + NAME MATCH (2-column like your PDF) ═══ -->
    <div class="row g-0 border-bottom" style="border-color:#dee2e6">
        <div class="col-md-7 p-4 border-end" style="border-color:#dee2e6">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;margin-bottom:12px">IDENTITY</div>
            <table style="font-size:12.5px;width:100%">
                <tr><td style="color:#94a3b8;padding:4px 12px 4px 0;width:120px;vertical-align:top;font-weight:500">Searched name</td>
                    <td style="font-weight:600;padding:4px 0">${d.fullname || '—'}</td></tr>
                ${v(d.slug) ? `<tr><td></td><td style="font-family:monospace;font-size:11px;color:#94a3b8;padding:0 0 6px">${d.slug}</td></tr>` : ''}
                <tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">Found name</td>
                    <td style="font-weight:600;padding:4px 0">${v(fn.full_name) || 'Not found'}</td></tr>
                ${fn.variants?.length ? `<tr><td></td><td style="font-size:11px;color:#64748b;padding:0 0 6px">${fn.variants.join(' · ')}</td></tr>` : ''}
                ${v(p.date_of_birth) ? `<tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">Date of birth</td><td style="padding:4px 0">${p.date_of_birth}${v(p.age) ? ' <span style="color:#94a3b8">(age ' + p.age + ')</span>' : ''}</td></tr>` : ''}
                ${v(p.nationality) ? `<tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">Nationality</td><td style="padding:4px 0">${p.nationality}</td></tr>` : ''}
                ${v(p.state_of_origin) ? `<tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">State of origin</td><td style="padding:4px 0">${p.state_of_origin}</td></tr>` : ''}
                ${v(p.marital_status) ? `<tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">Marital status</td><td style="padding:4px 0">${p.marital_status}</td></tr>` : ''}
                ${v(p.spouse) ? `<tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">Spouse</td><td style="padding:4px 0">${p.spouse}</td></tr>` : ''}
                ${v(p.religion) ? `<tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">Religion</td><td style="padding:4px 0">${p.religion}</td></tr>` : ''}
                ${v(p.political_party) ? `<tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">Political party</td><td style="padding:4px 0">${p.political_party}</td></tr>` : ''}
                ${v(p.net_worth) ? `<tr><td style="color:#94a3b8;padding:4px 12px 4px 0;font-weight:500">Net worth</td><td style="padding:4px 0">${p.net_worth}</td></tr>` : ''}
            </table>
            ${(p.current_positions||[]).length ? `
            <div style="margin-top:12px">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:6px">CURRENT POSITIONS</div>
                ${p.current_positions.map(pos => `<div style="font-size:12px;margin-bottom:4px"><span style="font-weight:600">${pos.title}</span>${pos.organisation ? ' — ' + pos.organisation : ''} <span style="color:#94a3b8;font-size:11px">${pos.from ? pos.from + ' → ' + (pos.to || 'present') : ''}</span></div>`).join('')}
            </div>` : ''}
            ${(p.previous_roles||[]).length ? `
            <div style="margin-top:10px">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:6px">PREVIOUS ROLES</div>
                ${p.previous_roles.map(pos => `<div style="font-size:12px;margin-bottom:3px;color:#475569">• ${pos.title}${pos.organisation ? ' — ' + pos.organisation : ''} <span style="color:#94a3b8;font-size:11px">${pos.from ? '(' + pos.from + (pos.to ? '–' + pos.to : '') + ')' : ''}</span></div>`).join('')}
            </div>` : ''}
            ${(p.schools_attended||[]).length ? `
            <div style="margin-top:10px">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:6px">EDUCATION</div>
                ${p.schools_attended.map(s => `<div style="font-size:12px;margin-bottom:3px;color:#475569">• ${s.name}${s.degree ? ' — ' + s.degree : ''} ${s.year ? '<span style="color:#94a3b8">(' + s.year + ')</span>' : ''}</div>`).join('')}
            </div>` : ''}
        </div>
        <div class="col-md-5 p-4">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;margin-bottom:12px">NAME MATCH CONFIDENCE</div>
            <div class="text-center mb-3">
                <div style="font-size:42px;font-weight:800;color:${nm.confidence==='HIGH'?'#16a34a':nm.confidence==='MEDIUM'?'#d97706':'#64748b'};line-height:1">${nm.score || 0}<span style="font-size:16px">%</span></div>
                <div style="font-size:12px;font-weight:700;color:${nm.confidence==='HIGH'?'#16a34a':nm.confidence==='MEDIUM'?'#d97706':'#64748b'};text-transform:uppercase;letter-spacing:1px;margin-top:4px">${nm.confidence || 'NO_MATCH'}</div>
            </div>
            ${nm.matched_parts?.length ? `<div style="font-size:11.5px;color:#475569;margin-bottom:4px">Matched: <span style="font-weight:600">${nm.matched_parts.join(' · ')}</span></div>` : ''}
            ${(nm.notes||[]).map(n => `<div style="font-size:11px;color:#94a3b8;line-height:1.5">${n}</div>`).join('')}
            ${(p.aliases||[]).length ? `
            <div style="margin-top:16px">
                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#94a3b8;margin-bottom:6px">ALIASES</div>
                <div class="d-flex flex-wrap gap-1">${p.aliases.map(a => `<span class="pas-kw">${a}</span>`).join('')}</div>
            </div>` : ''}
        </div>
    </div>

    <!-- ═══ SUMMARY ═══ -->
    ${v(p.summary) ? `
    <div class="p-4 border-bottom" style="border-color:#dee2e6">
        <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:#94a3b8;margin-bottom:8px">SUMMARY</div>
        <div style="font-size:12.5px;color:#475569;line-height:1.7">${p.summary}</div>
    </div>` : ''}

    <!-- ═══ OVERALL RISK + STATUS ═══ -->
    <div class="d-flex align-items-center justify-content-between p-3 px-4 border-bottom" style="background:${riskBg};border-color:#dee2e6">
        <div>
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:${riskColor}">OVERALL RISK LEVEL</div>
            <div style="font-size:22px;font-weight:800;color:${riskColor};display:flex;align-items:center;gap:8px">
                <span style="width:10px;height:10px;border-radius:50%;background:${riskColor};display:inline-block"></span>${risk}
            </div>
        </div>
        <div style="font-size:11px;color:#94a3b8">Screened ${screened}</div>
    </div>

    <!-- ═══ PEP / SANCTIONS / MEDIA STATUS (TRUE/FALSE badges) ═══ -->
    <div class="row g-0 border-bottom" style="border-color:#dee2e6">
        <div class="col-4 p-3 text-center border-end" style="border-color:#dee2e6">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;margin-bottom:6px">P.E.P</div>
            <div style="font-size:16px;font-weight:800;color:${pepDetected ? '#dc2626' : '#16a34a'}">${pepDetected ? 'TRUE' : 'FALSE'}</div>
            <div style="font-size:10px;color:#94a3b8">${pepMatches.length} match(es)</div>
        </div>
        <div class="col-4 p-3 text-center border-end" style="border-color:#dee2e6">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;margin-bottom:6px">SANCTIONS</div>
            <div style="font-size:16px;font-weight:800;color:${sanDetected ? '#dc2626' : '#16a34a'}">${sanDetected ? 'TRUE' : 'FALSE'}</div>
            <div style="font-size:10px;color:#94a3b8">${sanMatches.length} hit(s)</div>
        </div>
        <div class="col-4 p-3 text-center">
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:#94a3b8;margin-bottom:6px">ADVERSE MEDIA</div>
            <div style="font-size:16px;font-weight:800;color:${mediaDetected ? '#d97706' : '#16a34a'}">${mediaDetected ? 'TRUE' : 'FALSE'}</div>
            <div style="font-size:10px;color:#94a3b8">${mediaArticles.length} article(s)</div>
        </div>
    </div>

    <div class="p-4">
        <!-- ═══ PEP SECTION ═══ -->
        <div class="pas-sec mb-3">
            <div class="pas-sec-head d-flex align-items-center justify-content-between p-3" onclick="this.nextElementSibling.classList.toggle('d-none')">
                <div class="d-flex align-items-center gap-2">
                    <div class="pas-si pas-si-pep"><i class="bi bi-person-badge"></i></div>
                    <div>
                        <div class="pas-sec-title">Politically Exposed Person</div>
                        <div class="pas-sec-sub">Government officials & public figures</div>
                    </div>
                </div>
                <span class="${pepDetected ? 'pas-badge-hit' : 'pas-badge-ok'}">${d.pep_status || 'NO_MATCH'}</span>
            </div>
            <div class="p-0">
                ${pepMatches.map(m => `
                <div class="pas-pep-card p-3">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <a href="${m.url}" target="_blank" class="pas-pep-link">${m.title}</a>
                        <span class="pas-cf ${m.confidence}" style="flex-shrink:0;margin-left:8px">${m.confidence}</span>
                    </div>
                    <p class="pas-pep-snip mb-1">${m.snippet || ''}</p>
                    <div class="d-flex gap-1 flex-wrap">
                        ${(m.matched_keywords||[]).map(kw => `<span class="pas-kw">${kw}</span>`).join('')}
                    </div>
                </div>`).join('')}
                ${!pepDetected ? '<div class="text-center text-muted py-3" style="font-size:12px"><i class="bi bi-check-circle d-block mb-1" style="font-size:18px;color:#16a34a"></i>No PEP matches found</div>' : ''}
            </div>
        </div>

        <!-- ═══ SANCTIONS SECTION ═══ -->
        <div class="pas-sec mb-3">
            <div class="pas-sec-head d-flex align-items-center justify-content-between p-3" onclick="this.nextElementSibling.classList.toggle('d-none')">
                <div class="d-flex align-items-center gap-2">
                    <div class="pas-si pas-si-san"><i class="bi bi-shield-x"></i></div>
                    <div>
                        <div class="pas-sec-title">Sanctions Screening</div>
                        <div class="pas-sec-sub">OFAC SDN, Consolidated & trade.gov</div>
                    </div>
                </div>
                <span class="${sanDetected ? 'pas-badge-hit' : 'pas-badge-ok'}">${d.sanction_status || 'NOT_LISTED'}</span>
            </div>
            <div class="p-3 ${sanDetected ? '' : ''}">
                ${sanMatches.map(m => `
                <div class="pas-pep-card py-2">
                    <div class="pas-san-name">${m.name}</div>
                    <div class="d-flex gap-1 mt-1 flex-wrap">
                        <span class="pas-san-prog">${m.source}</span>
                        ${m.entity_type ? `<span style="font-size:10px;padding:2px 6px;border-radius:4px;background:#f1f5f9;color:#64748b">${m.entity_type}</span>` : ''}
                    </div>
                </div>`).join('')}
                ${!sanDetected ? '<div class="text-center text-muted py-2" style="font-size:12px"><i class="bi bi-check-circle d-block mb-1" style="font-size:18px;color:#16a34a"></i>Not listed on any sanctions list.</div>' : ''}
            </div>
        </div>

        <!-- ═══ ADVERSE MEDIA SECTION ═══ -->
        <div class="pas-sec mb-3">
            <div class="pas-sec-head d-flex align-items-center justify-content-between p-3" onclick="this.nextElementSibling.classList.toggle('d-none')">
                <div class="d-flex align-items-center gap-2">
                    <div class="pas-si pas-si-med"><i class="bi bi-newspaper"></i></div>
                    <div>
                        <div class="pas-sec-title">Adverse Media</div>
                        <div class="pas-sec-sub">News flagged for risk keywords</div>
                    </div>
                </div>
                <span class="${mediaDetected ? 'pas-badge-hit' : 'pas-badge-ok'}">${d.adverse_media_status || 'NO_MATCH'}</span>
            </div>
            <div class="p-0">
                ${mediaArticles.map(m => {
                    const d2 = m.published_at ? new Date(m.published_at) : null;
                    const day = d2 ? d2.getDate().toString().padStart(2,'0') : '';
                    const mon = d2 ? d2.toLocaleString('en',{month:'short'}).toUpperCase() : '';
                    const yr = d2 ? d2.getFullYear() : '';
                    return `
                    <div class="pas-med-card d-flex gap-3 p-3 align-items-start">
                        ${d2 ? `<div class="text-center flex-shrink-0" style="min-width:36px">
                            <div style="font-size:15px;font-weight:800;color:#1e293b;line-height:1">${day}</div>
                            <div style="font-size:8px;font-weight:700;letter-spacing:.5px;color:#94a3b8">${mon}</div>
                            <div style="font-size:8px;color:#cbd5e1">${yr}</div>
                        </div>` : ''}
                        <div>
                            <a href="${m.url}" target="_blank" class="pas-med-link">${m.title}</a>
                            <div style="font-size:10.5px;color:#94a3b8;margin-top:2px">${m.source_name || m.source || ''}</div>
                        </div>
                    </div>`;
                }).join('')}
                ${!mediaDetected ? '<div class="text-center text-muted py-3" style="font-size:12px"><i class="bi bi-check-circle d-block mb-1" style="font-size:18px;color:#16a34a"></i>No adverse media found</div>' : ''}
            </div>
        </div>
    </div>

    <!-- ═══ FOOTER ═══ -->
    <div class="pas-rfooter d-flex justify-content-between px-4 py-3">
        <span>Report ID: ${d.id || '—'} · Generated ${screened}</span>
        <span>P.A.S Screening System</span>
    </div>`;

    document.getElementById('pas-rcard').innerHTML = html;
}

function pasPrint() {
    const card = document.getElementById('pas-rcard');
    if (!card) return;
    const iframe = document.getElementById('pas-print-frame');
    const doc = iframe.contentDocument || iframe.contentWindow.document;
    doc.open();
    doc.write(`<html><head><title>P.A.S Screening Report — MoniSurv</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        body{font-family:'Inter',sans-serif;font-size:13px;padding:16px;color:#1a1e2c}
        .pas-rhead{background:#0d3b26;color:#fff;padding:24px}
        .pas-reyebrow{font-size:9px;font-weight:700;letter-spacing:1.4px;text-transform:uppercase;color:#3ecf8e}
        .pas-rname{font-size:20px;font-weight:700;color:#fff}
        .pas-rslug{font-size:10px;color:#94a3b8;font-family:monospace}
        .pas-rmeta-chip{font-size:10px;padding:2px 6px;border-radius:4px;background:rgba(255,255,255,.1);color:#94a3b8;display:inline-block}
        .pas-sec{border:1px solid #dee2e6;border-radius:8px;margin-bottom:10px;overflow:hidden;page-break-inside:avoid}
        .pas-sec-head{background:#f8f9fa;padding:10px 12px;border-bottom:1px solid #dee2e6;display:flex;justify-content:space-between;align-items:center}
        .pas-si{width:22px;height:22px;border-radius:5px;display:inline-flex;align-items:center;justify-content:center;font-size:11px;margin-right:6px}
        .pas-si-pep{background:#eff6ff;color:#3b82f6}.pas-si-san{background:#fff1f2;color:#f43f5e}.pas-si-med{background:#fffbeb;color:#f59e0b}
        .pas-sec-title{font-size:12px;font-weight:600}.pas-sec-sub{font-size:9px;color:#94a3b8}
        .pas-badge-hit{background:#fee2e2;color:#dc2626;font-size:9px;font-weight:700;padding:2px 8px;border-radius:4px;text-transform:uppercase}
        .pas-badge-ok{background:#dcfce7;color:#16a34a;font-size:9px;font-weight:700;padding:2px 8px;border-radius:4px;text-transform:uppercase}
        .pas-pep-card{border-bottom:1px solid #f1f5f9;padding:8px 12px}.pas-med-card{border-bottom:1px solid #f1f5f9;padding:8px 12px}
        .pas-pep-link{font-size:11px;color:#3b82f6;text-decoration:none}.pas-pep-snip{font-size:10px;color:#64748b}
        .pas-kw{font-size:8px;padding:1px 4px;border-radius:3px;background:#eff6ff;color:#3b82f6;display:inline-block;margin:1px}
        .pas-cf{font-size:8px;font-weight:700;padding:1px 4px;border-radius:3px}.pas-cf.HIGH{background:#fee2e2;color:#dc2626}.pas-cf.MEDIUM{background:#fef3c7;color:#d97706}.pas-cf.LOW{background:#f1f5f9;color:#64748b}
        .pas-san-name{font-size:11px;font-weight:600}.pas-san-prog{font-size:8px;padding:1px 4px;border-radius:3px;background:#fee2e2;color:#dc2626}
        .pas-med-link{font-size:11px;color:#1e293b;text-decoration:none}
        .pas-rfooter{background:#f8fafc;border-top:1px solid #dee2e6;padding:10px 16px;font-size:9px;color:#94a3b8;display:flex;justify-content:space-between}
        @media print{body{padding:0;font-size:11px}.pas-rhead{print-color-adjust:exact;-webkit-print-color-adjust:exact}}
    </style></head><body>${card.innerHTML}</body></html>`);
    doc.close();
    setTimeout(() => iframe.contentWindow.print(), 500);
}
