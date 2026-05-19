{{$request = request()}}
Package: {{$request.package}}

Module: {{$request.module|>string.uppercase.first}}

reset (used by command: {{binary()}} reset -application)
start
stop
guard
setup
