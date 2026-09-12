{{-- Risk Rating Navigation Tabs --}}
<div class="filter-bar mb-4">
    <a href="{{ route('risk-rating.index') }}" class="filter-pill {{ request()->routeIs('risk-rating.index') || request()->routeIs('risk-rating.view') || request()->routeIs('risk-rating.rate') ? 'active' : '' }}">
        <i class="bi bi-graph-up-arrow"></i> Risk Ratings
    </a>
    <a href="{{ route('risk-rating.manage-risk-level.index') }}" class="filter-pill {{ request()->routeIs('risk-rating.manage-risk-level.*') ? 'active' : '' }}">
        <i class="bi bi-layers"></i> Risk Levels
    </a>
    <a href="{{ route('risk-rating.manage-risk-profile.index') }}" class="filter-pill {{ request()->routeIs('risk-rating.manage-risk-profile.*') ? 'active' : '' }}">
        <i class="bi bi-file-earmark-bar-graph"></i> Risk Profiles
    </a>
    <a href="{{ route('risk-rating.reviews-due') }}" class="filter-pill {{ request()->routeIs('risk-rating.reviews-due') ? 'active' : '' }}">
        <i class="bi bi-calendar-check"></i> Reviews Due
        @php $overdueCount = \App\Models\Customer::where('review_status', 'overdue')->count(); @endphp
        @if($overdueCount > 0)
            <span class="count" style="background:#dc2626;color:#fff">{{ $overdueCount }}</span>
        @endif
    </a>
</div>
