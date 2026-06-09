{{$response = Package.Raxon.Ollama:Main:abort(flags(), options())}}
{{if($response)}}
{{$response|>object:'json'}}

{{/if}}