@extends('layouts.app')

@section('title', 'plan')

@push("css")

@endpush

@section('content')
<section id="hero">
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
                <div class="section-title">
                    <h1>plan</h1>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="thanks-content">

<div class="container">

    <div class="row">
        <div class="col-sm-6 col-md-12 col-lg-12">
            @foreach($plans as $plan)
                <div class="course-container mb-5">
                    <div class="media">
                        <div class="course-img">
                        <img src="" class="img-fluid mr-3" alt="{{ $plan->id }}">
                        </div>
                        <div class="media-body">
                            <h2><a href="{{ route('plan.show', $plan->id) }}">{{$plan->title?? '' }}</a></h2>
                                 <p>{{$plan->description}}</p>
                            <div>
                                <a href="{{ route('plan.show', $plan->id) }}" class="btn btn-primary mt-3">Go to plan <i class="fas fa-angle-double-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                <hr>
            @endforeach
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
        <div class="course-pagination">
        &nbsp;
        </div>

        </div>
    </div>

</div>

</section>



@endsection

@push("js")

@endpush
