@extends('errors::minimal')

@section('title', __('Service Unavailable'))
@section('code', '503')
@section('message', __('Service is temporarily unavailable due to maintenance. Please check back soon.'))
