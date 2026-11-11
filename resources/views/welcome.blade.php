<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<section id="auth" hidden>
    <h1>{{ config('app.name') }}</h1>
    <input id="name" placeholder="Name (sign up only)" autocomplete="name">
    <input id="email" type="email" placeholder="Email" autocomplete="email">
    <input id="password" type="password" placeholder="Password (min 8)" autocomplete="current-password">
    <div class="row"><button id="login">Log in</button><button id="register" class="alt">Sign up</button></div>
    <p id="auth-error" class="err"></p>
</section>

<section id="app" hidden>
    <aside>
        <header><b id="me"></b> <span id="conn" class="bad">connecting...</span> <button id="logout" class="alt">Log out</button></header>
        <h3>Friends</h3>
        <ul id="friends"></ul>
        <form id="add-friend"><input id="friend-email" type="email" placeholder="Add friend by email" required><button>Add</button></form>
        <h3>Groups</h3>
        <ul id="groups"></ul>
        <form id="new-group">
            <input id="group-name" placeholder="New group name (max 100 members)" required>
            <div id="members"></div>
            <button>Create</button>
        </form>
    </aside>
    <main>
        <header><b id="title">Pick a conversation</b></header>
        <button id="more" class="alt" hidden>Load older messages</button>
        <div id="messages"></div>
        <form id="composer" hidden><input id="text" placeholder="Type a message" autocomplete="off"><button>Send</button></form>
    </main>
</section>
</body>
</html>
