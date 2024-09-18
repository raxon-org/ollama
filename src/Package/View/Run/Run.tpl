{{R3M}}
{{$response = Package.Raxon.Ollama:Main:run(flags(), options())}}
{{if($response)}}
{{$response|object:'json'}}

{{/if}}