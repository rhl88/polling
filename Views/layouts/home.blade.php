<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', '投票')</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <style>
        :root {
            --poll-primary: #4f6ef7;
            --poll-primary-dark: #3b5bdb;
            --poll-bg: #f5f6fa;
            --poll-card: #ffffff;
            --poll-text: #333333;
            --poll-text-light: #888888;
            --poll-border: #e8e9ee;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            background: var(--poll-bg);
            color: var(--poll-text);
            min-height: 100vh;
        }
        a { text-decoration: none; color: var(--poll-primary); }
        .poll-header {
            background: var(--poll-card);
            border-bottom: 1px solid var(--poll-border);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .poll-header .brand {
            font-size: 20px;
            font-weight: 700;
            color: var(--poll-primary);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .poll-header .brand .logo {
            width: 28px;
            height: 28px;
            border-radius: 6px;
            background: var(--poll-primary);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }
        .poll-header .actions { display: flex; align-items: center; gap: 14px; font-size: 14px; }
        .poll-header .user-name { color: var(--poll-text-light); }
        .poll-main {
            max-width: 860px;
            margin: 0 auto;
            padding: 24px 16px 60px;
        }
        .poll-footer {
            text-align: center;
            color: var(--poll-text-light);
            font-size: 12px;
            padding: 20px 0 30px;
        }
    </style>
    @stack('styles')
</head>
<body>
<header class="poll-header">
    <a class="brand" href="/cmspro/polling">
        <span class="logo">&#10003;</span>
        投票
    </a>
    <div class="actions">
        @if(auth('web')->check())
            <span class="user-name">{{ auth('web')->user()->nickname ?: auth('web')->user()->username }}</span>
            <a href="/user/cmspro/polling">我的投票</a>
        @else
            <a href="/user/cmspro/polling">登录后参与</a>
        @endif
    </div>
</header>

<main class="poll-main">
    @yield('content')
</main>

<footer class="poll-footer">Powered by CmsPro 投票系统</footer>

@php $siteAnalytics = \App\Models\ConfigItem::where('code', 'site_analytics')->value('value'); @endphp
@if($siteAnalytics)
{!! $siteAnalytics !!}
@endif
@stack('scripts')
</body>
</html>
