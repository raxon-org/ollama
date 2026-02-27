{{$register = Package.Raxon.Ollama:Init:register()}}
{{if(!is.empty($register))}}
{{Package.Raxon.Ollama:Import:role.system()}}
{{Package.Raxon.Ollama:Setup:install.ollama()}}
{{/if}}
