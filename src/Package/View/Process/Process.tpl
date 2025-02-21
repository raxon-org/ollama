{{$response = Package.Raxon.Ollama:Main:process(flags(), options())}}
{{if($response)}}
{{$response|object:'json'}}

{{/if}}