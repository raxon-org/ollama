{{$request = request()}}
Package: {{$request.package}}

Module: {{$request.module|>string.uppercase.first}}

reset (used by app reset -application)
start
stop
guard
setup
