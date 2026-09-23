<template>
    <nav class="navBar">
        <ul class="navBar__link">
            <li><router-link :to="{ name: 'home'}">Home</router-link></li>
            <li><router-link :to="{ name: 'contacts'}">Contacts</router-link></li>
            <li><router-link :to="{ name: 'helloworld'}">Hello World</router-link></li>
        </ul>

    </nav>
</template>

<script>
export default {
    name: 'Navbar'
}
</script>