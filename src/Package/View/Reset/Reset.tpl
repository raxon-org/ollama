{{$response = Package.Raxon.Ollama:Main:reset(flags(), options())}}
{{if($response)}}
{{$response|>object:'json'}}

{{/if}}