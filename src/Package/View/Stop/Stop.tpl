{{R3M}}
{{$response = Package.Raxon.Ollama:Main:stop(flags(), options())}}
{{if($response)}}
{{$response|object:'json'}}

{{/if}}