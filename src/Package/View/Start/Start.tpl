{{$response = Package.Raxon.Ollama:Main:start(flags(), options())}}
{{if($response)}}
{{$response|>object:'json'}}

{{/if}}