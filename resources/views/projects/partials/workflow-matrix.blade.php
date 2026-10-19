@if($workflowMatrix)
<section class="master-card master-card--flat master-section project-detail-card project-command-matrix" id="workflow-command-matrix" aria-labelledby="workflowMatrixTitle">
    <div class="master-section-head">
        <div><h2 class="master-section-title" id="workflowMatrixTitle">Execution control matrix</h2><p class="master-sub">Every project control and independent product lane in one source-controlled view. Quantities and money come from the latest gate evaluations.</p></div>
        <div class="project-matrix-summary"><span>{{ $workflowMatrix['summary']['lanes'] }} lanes</span><span>{{ $workflowMatrix['summary']['current'] }} current</span><span>{{ $workflowMatrix['summary']['blocked'] }} blocked</span><span>{{ $workflowMatrix['summary']['waiting'] }} waiting</span><span>{{ $workflowMatrix['summary']['overridden'] }} overrides</span></div>
    </div>
    <div class="project-matrix-legend" aria-label="Matrix status legend">
        @foreach(['completed'=>'Complete','active'=>'In progress','ready'=>'Ready','waiting'=>'Waiting','blocked'=>'Blocked','overridden'=>'Manager override','planned'=>'Not reached'] as $key=>$label)<span class="is-{{ $key }}"><i aria-hidden="true"></i>{{ $label }}</span>@endforeach
        <span class="is-current"><i aria-hidden="true"></i>Current lane action</span>
    </div>
    <div class="master-table-wrap project-matrix-wrap">
        <table class="master-table project-matrix-table">
            <thead><tr><th scope="col">Execution lane</th>@foreach($workflowMatrix['stage_summary'] as $stage)<th scope="col"><span>{{ $stage['label'] }}</span><small>{{ $stage['done'] }}/{{ $stage['applicable'] }} cleared</small></th>@endforeach</tr></thead>
            <tbody>
                @foreach($workflowMatrix['rows'] as $row)
                    <tr class="{{ $row['has_risk']?'has-risk':'' }}">
                        <th scope="row"><div class="project-matrix-lane"><strong>{{ $row['label'] }}</strong><span>{{ $row['sub_label'] }}</span><small>{{ $row['coordinator'] }} · {{ $row['progress'] }}%</small><progress max="100" value="{{ $row['progress'] }}" aria-label="{{ $row['label'] }} progress">{{ $row['progress'] }}%</progress></div></th>
                        @foreach($workflowMatrix['stages'] as $stageKey=>$stageLabel)
                            @php($cell=$row['cells'][$stageKey])
                            <td class="project-matrix-cell is-{{ $cell['state'] }} {{ $cell['is_current']?'is-current':'' }}">
                                @if($cell['state']==='not_applicable')<span class="project-matrix-na">—</span>@else
                                    <div class="project-matrix-cell-head"><span>{{ $cell['label'] }}</span>@if($cell['exception_count'])<a href="{{ route('projects.exceptions.index',['status'=>'active','search'=>$project->project_number]) }}" title="{{ $cell['exception_count'] }} active exception(s)"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>{{ $cell['exception_count'] }}</a>@endif</div>
                                    @foreach($cell['steps'] as $matrixStep)
                                        <div class="project-matrix-step" title="{{ $matrixStep['summary'] ?: $matrixStep['blocked_reason'] ?: $matrixStep['title'] }}">
                                            <strong>{{ $matrixStep['title'] }}</strong>
                                            @if($matrixStep['metric'])
                                                @php($metric=$matrixStep['metric'])
                                                <progress class="project-matrix-meter" max="100" value="{{ $metric['percent'] }}" aria-label="{{ $matrixStep['title'] }} coverage">{{ $metric['percent'] }}%</progress>
                                                <small>
                                                    @if($metric['type']==='money'){{ \App\Helpers\CommonHelper::amount($metric['covered'],$metric['currency']) }} / {{ \App\Helpers\CommonHelper::amount($metric['required'],$metric['currency']) }}
                                                    @elseif($metric['type']==='products'){{ number_format($metric['covered'],0) }} / {{ number_format($metric['required'],0) }} products
                                                    @else{{ rtrim(rtrim(number_format($metric['covered'],3,'.',''),'0'),'.') }} / {{ rtrim(rtrim(number_format($metric['required'],3,'.',''),'0'),'.') }}
                                                    @endif
                                                </small>
                                            @endif
                                            @if($matrixStep['approval'])<small class="project-matrix-approval {{ $matrixStep['approval']['overdue']?'is-overdue':'' }}">V{{ $matrixStep['approval']['version'] }} · {{ $matrixStep['approval']['label'] }}@if($matrixStep['approval']['due_at']) · {{ $matrixStep['approval']['due_at'] }}@endif</small>@endif
                                            @if($matrixStep['feedback'])<small class="project-matrix-approval">{{ str($matrixStep['feedback']['state'])->headline() }}@if($matrixStep['feedback']['band']) · {{ str($matrixStep['feedback']['band'])->headline() }}@endif · {{ $matrixStep['feedback']['reminders'] }}/{{ \App\Models\FeedbackRequest::MAX_REMINDERS }} reminders · {{ $matrixStep['feedback']['open_actions'] }} open action(s)</small>@endif
                                            @if($matrixStep['blocked_reason'])<small class="project-matrix-reason">{{ \Illuminate\Support\Str::limit($matrixStep['blocked_reason'],70) }}</small>@elseif($matrixStep['summary'] && in_array($matrixStep['state'],['blocked','waiting','overridden'],true))<small class="project-matrix-reason">{{ \Illuminate\Support\Str::limit($matrixStep['summary'],70) }}</small>@endif
                                            @if($matrixStep['evaluated_at'])<small class="project-matrix-check">Checked {{ $matrixStep['evaluated_at'] }}@if($matrixStep['fingerprint']) · {{ substr($matrixStep['fingerprint'],0,7) }}@endif</small>@endif
                                        </div>
                                    @endforeach
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="project-matrix-footnote"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> A manager override is shown separately from a passed control. “Not reached” means the lane exists but its prerequisite is still open; it does not mean the control passed.</p>
</section>
@endif
