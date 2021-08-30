@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-12">
            <div class="home-content">
                <h1>Welcome</h1>
                <p>Payment integration.</p>
                <p><strong>Migrate database tables:</strong></p>
                <p><pre>php artisan migrate</pre></p>
                <p><strong>Seed database with dummy data:</strong></p>
                <p><pre>php artisan db:seed</pre></p>
                <p><strong>Admin login details:</strong></p>
                <p>URL: http://localhost/laravelpaymentstarter/login</p>
                <p>User: admin@gmail.com</p>
                <p>Password: 12341234</p>
            </div>
        </div>
    </div>
</div>
@endsection
