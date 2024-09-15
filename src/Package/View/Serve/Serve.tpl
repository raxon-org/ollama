{{R3M}}
{{$response = Package.Raxon.Ollama:Main:serve(flags(), options())}}
{{if($response)}}
{{$response|object:'json'}}

{{/if}}