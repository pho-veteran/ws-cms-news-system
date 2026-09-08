/**
 * Keep the comment form anchored above the discussion while supporting replies.
 */
export function initCommentReply(root = document) {
  const formWrap = root.querySelector('[data-pgds="comment-form"]');
  const commentSection = formWrap?.closest('.pgds-comments');
  const form = formWrap?.querySelector('form');
  const textarea = form?.querySelector('textarea[name="comment"]');
  const parentInput = form?.querySelector('input[name="comment_parent"]');
  const replyContext = formWrap?.querySelector('[data-pgds="reply-context"]');
  const replyText = replyContext?.querySelector('[data-pgds="reply-text"]');
  const cancelButton = replyContext?.querySelector('[data-pgds="cancel-reply"]');

  if (!commentSection || !textarea || !parentInput || !replyContext || !replyText || !cancelButton) {
    return;
  }

  const defaultPlaceholder = textarea.getAttribute('placeholder') || '';
  const replyLabel = formWrap.dataset.replyLabel || 'Replying to';
  const replyPlaceholder = formWrap.dataset.replyPlaceholder || 'Reply to';

  const resetReply = () => {
    parentInput.value = '0';
    replyContext.hidden = true;
    replyText.textContent = '';
    textarea.setAttribute('placeholder', defaultPlaceholder);
  };

  commentSection.addEventListener('click', (event) => {
    const replyLink = event.target.closest('[data-pgds="comment-reply"]');
    if (!replyLink) return;

    event.preventDefault();
    const commentId = replyLink.dataset.commentId;
    const author = replyLink.dataset.commentAuthor || '';
    if (!commentId) return;

    parentInput.value = commentId;
    replyText.textContent = `${replyLabel} ${author}`;
    replyContext.hidden = false;
    textarea.setAttribute('placeholder', `${replyPlaceholder} ${author}…`);
    formWrap.scrollIntoView({ behavior: 'smooth', block: 'center' });
    textarea.focus({ preventScroll: true });
  });

  cancelButton.addEventListener('click', () => {
    resetReply();
    textarea.focus();
  });
}
