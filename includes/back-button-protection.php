<script>
window.addEventListener("pageshow", function (event) {
    const navigationEntries = performance.getEntriesByType("navigation");
    const navigationType = navigationEntries.length > 0 ? navigationEntries[0].type : "";

    if (event.persisted || navigationType === "back_forward") {
        window.location.reload();
    }
});
</script>