<style>
    .chat-shell { overflow: hidden; }
    .chat-body { height: calc(100vh - 230px); min-height: 520px; }
    .chat-body-system { height: calc(100vh - 140px); }
    .chat-sidebar { height: 100%; border-right: 1px solid #dee2e6; background: #f8f9fa; display: flex; flex-direction: column; min-width: 210px; }
    .chat-project-head { padding: 12px; border-bottom: 1px solid #dee2e6; background: #fff; }
    .chat-sidebar-title { padding: 8px 12px; border-bottom: 1px solid #dee2e6; font-size: 12px; color: #8a94a6; }
    .chat-member-list { flex: 1; overflow-y: auto; }
    .chat-member-item { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-bottom: 1px solid #eef0f3; color: inherit; text-decoration: none; }
    .chat-member-item:hover { background: #e9ecef; }
    .chat-member-item.active { background: #e3f2fd; border-left: 3px solid #1976d2; padding-left: 9px; }
    .chat-avatar-wrap { position: relative; flex: 0 0 auto; }
    .chat-avatar { width: 40px; height: 40px; border-radius: 50%; color: #fff; display: inline-flex; align-items: center; justify-content: center; font-size: 16px; flex: 0 0 auto; }
    .chat-avatar-img { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; display: block; }
    .chat-avatar-img.offline { filter: grayscale(100%); opacity: .5; }
    .chat-avatar.offline { opacity: .5; }
    .chat-head-avatar { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
    .online-dot { position: absolute; right: 0; bottom: 0; width: 10px; height: 10px; border-radius: 50%; border: 2px solid #fff; }
    .online-dot.online { background: #4caf50; }
    .online-dot.offline { background: #bdbdbd; }
    .chat-member-meta { display: flex; flex-direction: column; min-width: 0; }
    .chat-member-name { font-size: 14px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-member-sub { font-size: 12px; color: #8a94a6; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chat-main { height: 100%; display: flex; flex-direction: column; min-width: 0; }
    .chat-main-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 16px; border-bottom: 1px solid #dee2e6; background: #fff; }
    .chat-messages { flex: 1; min-height: 0; overflow-y: auto; background: #f5f5f5; padding: 16px; }
    .chat-container { display: flex; flex-direction: column; }
    .chat-message { display: flex; align-items: flex-start; margin-bottom: 16px; gap: 8px; }
    .chat-message.mine { flex-direction: row-reverse; }
    .message-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex: 0 0 auto; background: #6c757d; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 16px; }
    .message-content { max-width: 68%; min-width: 0; }
    .message-info { display: flex; align-items: center; gap: 6px; margin-bottom: 3px; font-size: 11px; color: #999; }
    .chat-message.mine .message-info { justify-content: flex-end; }
    .message-username { font-weight: 600; color: #6b7280; }
    .message-bubble { display: inline-block; padding: 8px 14px; border-radius: 8px; line-height: 1.5; font-size: 14px; white-space: pre-wrap; word-break: break-word; background: #fff; border: 1px solid #e8e8e8; }
    .chat-message.mine .message-bubble { background: #95ec69; border-color: #86d65d; }
    .chat-message.mine .message-content { text-align: right; }
    .file-attachment { display: flex; align-items: center; gap: 8px; padding: 8px 12px; margin-top: 4px; border-radius: 6px; background: rgba(0,0,0,.06); font-size: 13px; cursor: pointer; text-align: left; }
    .file-attachment:hover { background: rgba(0,0,0,.1); }
    .file-attachment i { color: #1976d2; }
    .chat-image-attachment { display: block; max-width: 220px; max-height: 180px; border-radius: 6px; border: 1px solid #e0e3e8; object-fit: contain; background: #fff; }
    .chat-file-preview { display: flex; align-items: center; gap: 8px; padding: 8px 10px; margin-bottom: 8px; border: 1px solid #e5e7eb; border-radius: 6px; background: #f8f9fa; }
    .chat-file-preview img { width: 38px; height: 38px; border-radius: 4px; object-fit: cover; border: 1px solid #dee2e6; }
    .chat-delete { margin-top: 3px; }
    .chat-composer { position: relative; padding: 12px 16px; border-top: 1px solid #dee2e6; background: #fff; }
    .emoji-panel { position: absolute; left: 16px; right: 16px; bottom: 100%; z-index: 20; background: #fff; border: 1px solid #e5e5e5; border-radius: 8px; padding: 10px; max-height: 190px; overflow-y: auto; box-shadow: 0 -2px 8px rgba(0,0,0,.1); }
    .emoji-grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 4px; }
    .emoji-item { font-size: 22px; cursor: pointer; text-align: center; padding: 4px; border-radius: 4px; }
    .emoji-item:hover { background: #f0f0f0; }
    .min-w-0 { min-width: 0; }
    @media (max-width: 767.98px) {
        .chat-sidebar { display: none; }
        .chat-main { flex: 0 0 100%; max-width: 100%; }
    }
</style>
