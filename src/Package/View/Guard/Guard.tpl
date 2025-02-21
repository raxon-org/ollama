{{$response = Package.Raxon.Ollama:Main:guard(flags(), options())}}
{{if($response)}}
{{$response|object:'json'}}

{{/if}}