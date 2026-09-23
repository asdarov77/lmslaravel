const localeDate = date =>
{
    return new Date(date).toLocaleString('ru', {
        hour: "numeric",
        minute: "numeric",
        year: "numeric",
        month: "long",
        day: "numeric",
    })
}

export default localeDate
