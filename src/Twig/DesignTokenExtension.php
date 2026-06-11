<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

class DesignTokenExtension extends AbstractExtension implements GlobalsInterface
{
    public function getGlobals(): array
    {
        return [
            'tokens' => [
                'card' => [
                    'soft' => 'bg-slate-50/10 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-700 transition-colors duration-500',
                    'primary' => 'bg-primary/10 dark:bg-primary/20 border border-primary/20',
                    'flat' => 'bg-transparent',
                ],
                'text' => [
                    'title' => 'text-slate-800 dark:text-slate-100',
                    'body' => 'text-slate-600 dark:text-slate-300',
                    'muted' => 'text-slate-500 dark:text-slate-400',
                    'label' => 'text-xs uppercase tracking-wider text-slate-400 dark:text-slate-300 font-bold',
                ],
                'icon' => [
                    'circle' => 'w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-200 flex items-center justify-center text-primary dark:text-slate-900 shrink-0',
                ],
                'button' => [
                    'primary' => 'bg-primary hover:bg-blue-600 text-white dark:text-slate-800 px-6 py-3 rounded-xl font-semibold flex items-center justify-center gap-2 transition-all duration-300 shadow-xl shadow-blue-500/20',
                    'secondary' => 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-800 px-6 py-3 rounded-xl font-semibold flex items-center justify-center gap-2 transition-all duration-300',
                ],
            ],
        ];
    }
}
