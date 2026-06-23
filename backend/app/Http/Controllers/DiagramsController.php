<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class DiagramsController extends Controller
{
    /**
     * Страница с архитектурными диаграммами: тот же docs/diagrams.md, отрисованный mermaid.js.
     */
    public function __invoke(): View
    {
        $path = (string) config('docsign.diagrams_path');
        $markdown = is_file($path)
            ? (string) file_get_contents($path)
            : '# Диаграммы недоступны';

        return view('docs.diagrams', ['markdown' => $markdown]);
    }
}
