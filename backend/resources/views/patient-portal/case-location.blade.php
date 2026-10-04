@if(!empty($caseLocation))
<section class="card pad"><h2>{{ __('patient_portal.location_title') }}</h2><p>{{ app(\App\Domain\Patients\IranLocations::class)->provinces()[$caseLocation->province] ?? $caseLocation->province }} · {{ $caseLocation->city }}@if($caseLocation->neighborhood) · {{ $caseLocation->neighborhood }}@endif</p>
@if($caseLocation->address)<p dir="auto">{{ $caseLocation->address }}</p>@endif
<p class="hint">{{ __('patient_portal.coverage') }}</p></section>
@endif
