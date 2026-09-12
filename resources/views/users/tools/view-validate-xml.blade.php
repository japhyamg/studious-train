@extends('layouts.app')
@section('title', 'XML Validator')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Dashboard</a><span class="sep">/</span>
    <span class="current">XML Validator</span>
@endsection

@section('content')
<div class="page-heading">
    <div>
        <h4><i class="bi bi-file-earmark-code" style="color:var(--green-600);opacity:.6"></i> XML Validator</h4>
        <div class="heading-subtitle">Validate goAML XML reports against an XSD schema</div>
    </div>
</div>

<div class="row g-4">
    {{-- Left: Upload & Validate --}}
    <div class="col-lg-6">
        {{-- Validate XML --}}
        <div class="card mb-4">
            <div class="card-header"><span><i class="bi bi-check-circle me-2"></i> Validate XML File</span></div>
            <div class="card-body">
                <form method="POST" action="{{ route('tools.validate-xml') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Select XML File</label>
                        <input type="file" name="xml_file" class="form-control form-control-sm" accept=".xml" required>
                        <div class="form-text">Upload a goAML STR or CTR XML file (max 10MB)</div>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-check-circle me-1"></i> Validate</button>
                </form>
            </div>
        </div>

        {{-- XSD Schema Management --}}
        <div class="card">
            <div class="card-header">
                <span><i class="bi bi-file-earmark-ruled me-2"></i> XSD Schema</span>
                @if($hasXsd)
                <span class="status-dot active" style="font-size:10px">Active</span>
                @else
                <span class="status-dot {{ $xsdName ? 'active' : 'warning' }}" style="font-size:10px">{{ $xsdName ? 'Default' : 'Not configured' }}</span>
                @endif
            </div>
            <div class="card-body">
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:14px">
                    Upload the XSD schema file that XML reports will be validated against. This is typically the goAML schema provided by NFIU.
                </p>

                {{-- Current schema info --}}
                <div class="p-3 rounded-3 mb-3 d-flex align-items-center gap-3" style="background:#faf8f2;border:1px solid var(--border-light)">
                    <div style="width:40px;height:40px;border-radius:10px;background:{{ $hasXsd || $xsdName ? 'var(--green-50)' : '#fef3c7' }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-file-earmark-ruled" style="font-size:18px;color:{{ $hasXsd || $xsdName ? 'var(--green-700)' : '#b45309' }}"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div style="font-size:13px;font-weight:600">
                            @if($hasXsd)
                                Custom XSD uploaded
                            @elseif($xsdName)
                                {{ $xsdName }}
                            @else
                                No XSD schema configured
                            @endif
                        </div>
                        <div style="font-size:11px;color:var(--text-muted)">
                            @if($hasXsd)
                                Uploaded schema will be used for all validations
                            @elseif($xsdName)
                                Default schema found in storage
                            @else
                                Upload an XSD file to enable validation
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Upload XSD --}}
                <form method="POST" action="{{ route('tools.upload-xsd') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Upload XSD Schema</label>
                        <input type="file" name="xsd_file" class="form-control form-control-sm" accept=".xsd,.xml" required>
                        <div class="form-text">goAML XSD schema file (.xsd) — max 5MB</div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-upload me-1"></i> Upload Schema</button>
                        @if($hasXsd)
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="deleteXsd()"><i class="bi bi-trash me-1"></i> Remove Custom</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Right: Validation Result --}}
    <div class="col-lg-6">
        @if(session('validation_result') !== null)
        <div class="card">
            <div class="card-header">
                <span>
                    <i class="bi bi-{{ session('validation_result') ? 'check-circle-fill' : 'x-circle-fill' }} me-2" style="color:{{ session('validation_result') ? 'var(--green-700)' : '#dc2626' }}"></i>
                    Validation Result
                </span>
                @if(session('file_name'))
                <span class="badge" style="background:#faf8f2;color:var(--text-secondary);font-size:10px;border:1px solid var(--border-light)">{{ session('file_name') }}</span>
                @endif
            </div>
            <div class="card-body">
                {{-- Status banner --}}
                <div class="p-3 rounded-3 mb-3" style="background:{{ session('validation_result') ? 'var(--green-50)' : '#fef2f2' }};border:1px solid {{ session('validation_result') ? 'var(--green-100)' : '#fecaca' }}">
                    <div class="d-flex align-items-center gap-2">
                        <i class="bi bi-{{ session('validation_result') ? 'check-circle-fill' : 'exclamation-circle-fill' }}" style="font-size:20px;color:{{ session('validation_result') ? 'var(--green-700)' : '#dc2626' }}"></i>
                        <div>
                            <div style="font-size:14px;font-weight:700;color:{{ session('validation_result') ? 'var(--green-800)' : '#b91c1c' }}">
                                {{ session('message', session('validation_result') ? 'XML is valid!' : 'Validation failed') }}
                            </div>
                            @if(session('xsd_used'))
                            <div style="font-size:11px;color:var(--text-muted)">Validated against: {{ session('xsd_used') }}</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Errors list --}}
                @if(session('xml_errors') && count(session('xml_errors')))
                <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:8px">
                    <i class="bi bi-exclamation-triangle me-1"></i> {{ count(session('xml_errors')) }} error{{ count(session('xml_errors')) > 1 ? 's' : '' }} found
                </div>
                <div class="p-3 rounded-3" style="background:#faf8f2;border:1px solid var(--border-light);max-height:400px;overflow-y:auto">
                    @foreach(session('xml_errors') as $idx => $e)
                    <div class="d-flex gap-2 {{ !$loop->last ? 'mb-2 pb-2 border-bottom' : '' }}" style="font-size:12px">
                        <span class="badge bg-danger" style="font-size:9px;padding:2px 6px;height:fit-content;margin-top:2px">{{ $idx + 1 }}</span>
                        <span style="color:var(--text-secondary);line-height:1.5">{{ $e }}</span>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        @else
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-file-earmark-code"></i></div>
                    <div class="empty-state-title">No validation results</div>
                    <div class="empty-state-text">Upload an XML file and click validate to check it against the XSD schema.</div>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
function deleteXsd() {
    if (!confirm('Remove the custom XSD schema? The system will fall back to the default schema if available.')) return;
    fetch('{{ route("tools.delete-xsd") }}', {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
    }).then(() => location.reload());
}
</script>
@endpush
