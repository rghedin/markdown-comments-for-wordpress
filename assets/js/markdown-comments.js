document.addEventListener('DOMContentLoaded', function() {
    var textarea = document.querySelector('#comment');
    if (textarea && typeof markdownCommentsHelp !== 'undefined') {
        var helpText = document.createElement('div');
        helpText.className = 'markdown-comments-help';
        helpText.innerHTML = markdownCommentsHelp.text;
        textarea.parentNode.insertBefore(helpText, textarea.nextSibling);
    }
});
