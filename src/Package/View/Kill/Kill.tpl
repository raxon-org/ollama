{{$response = Package.Raxon.Ollama:Main:kill(flags(), options())}}
{{if($response)}}
{{$response|>object:'json'}}

{{/if}}