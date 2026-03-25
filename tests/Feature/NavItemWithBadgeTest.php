<?php

test('nav-item-with-badge component renders as navlist item by default', function () {
    $view = $this->blade(
        '<x-echochat::nav-item-with-badge href="#" badge="5">Inbox</x-echochat::nav-item-with-badge>'
    );

    $view->assertSee('Inbox');
    $view->assertSee('5');
    $view->assertSee('data-flux-navlist-badge');
});

test('nav-item-with-badge component renders as navbar item when type is navbar', function () {
    $view = $this->blade(
        '<x-echochat::nav-item-with-badge type="navbar" href="#" badge="10">Notifications</x-echochat::nav-item-with-badge>'
    );

    $view->assertSee('Notifications');
    $view->assertSee('10');
});

test('nav-item-with-badge component does not show badge if count is 0', function () {
    $view = $this->blade(
        '<x-echochat::nav-item-with-badge :badge="0">Empty</x-echochat::nav-item-with-badge>'
    );

    $view->assertSee('Empty');
    $view->assertDontSee('data-flux-navlist-badge');
});

test('nav-item-with-badge component supports badge color', function () {
    $view = $this->blade(
        '<x-echochat::nav-item-with-badge badge="1" badge-color="blue">Blue Badge</x-echochat::nav-item-with-badge>'
    );

    $view->assertSee('Blue Badge');
    // バッジの色がクラス名や属性に反映されていることを確認（Fluxの生成するHTMLに基づく）
    $view->assertSee('text-blue-800');
});
