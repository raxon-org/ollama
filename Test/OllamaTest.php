<?php

// Example test case

test('ollama', function () {
    $string = "Hello, world!";
    expect($string)->toContain("world");
});
