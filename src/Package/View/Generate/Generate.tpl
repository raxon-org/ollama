{{R3M}}
{{$response = Package.Raxon.Ollama:Main:generate(flags(), options())}}
{{if($response)}}
{{$response|object:'json'}}

{{/if}}