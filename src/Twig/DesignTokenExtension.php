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
                    'soft' => 'bg-slate-50/10 dark:bg-slate-700/50 border border-slate-300 dark:border-slate-600 transition-colors duration-500',
                    'primary' => 'bg-primary/10 dark:bg-primary/20 border border-primary/20',
                    'flat' => 'bg-transparent',
                ],
                'text' => [
                    'title' => 'text-slate-800 dark:text-slate-100',
                    'body' => 'text-slate-600 dark:text-slate-300',
                    'muted' => 'text-slate-900 dark:text-slate-400',
                    'label' => 'text-xs uppercase tracking-wider text-slate-400 dark:text-slate-300 font-bold',
                ],
                'icon' => [
                    'circle' => 'w-12 h-12 rounded-full bg-blue-50 dark:bg-blue-200 flex items-center justify-center text-primary dark:text-slate-900 shrink-0',
                    'service_card' => [
                        'container' => 'absolute bottom-3 left-3 z-20 w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-white/90 dark:bg-slate-800/90 backdrop-blur-sm shadow-lg flex items-center justify-center text-primary dark:text-blue-400',
                        'img' => 'w-4 h-4 sm:w-5 sm:h-5 object-contain',
                        'format' => 'service-icon',
                    ],
                ],
                'button' => [
                    'primary' => 'bg-primary hover:bg-blue-600 text-white dark:!text-slate-900 px-4 sm:px-6 py-3 rounded-xl font-semibold flex items-center justify-center gap-2 transition-all duration-300 shadow-xl shadow-blue-500/20 text-sm sm:text-base',
                    'secondary' => 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-700 text-slate-800 dark:!text-slate-900 px-4 sm:px-6 py-3 rounded-xl font-semibold flex items-center justify-center gap-2 transition-all duration-300 text-sm sm:text-base',
                ],
                'menu' => [
                    'mobile' => [
                        'container' => 'bg-white/90 dark:bg-slate-800/90',
                        'backdrop' => 'fixed inset-0 z-[99998] bg-slate-900/50 backdrop-blur-sm',
                        'drawer' => 'fixed inset-y-0 right-0 z-[99999] w-full md:max-w-md bg-white dark:bg-slate-900 shadow-2xl transition-all duration-300',
                        'header' => 'flex justify-between items-center mb-10 border-b border-slate-200 dark:border-slate-800 pb-6',
                        'link' => 'text-2xl font-bold text-slate-800 dark:text-slate-300 hover:text-primary transition-colors',
                        'link_active' => 'text-primary',
                        'footer_border' => 'mt-auto pt-8 border-t border-slate-200 dark:border-slate-800',
                        'close_button' => 'p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors text-black dark:text-white',
                    ],
                ],
            ],
        ];
    }
}
