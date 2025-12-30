export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // 检查路径是否以 /backend/api/ 开头
    if (url.pathname.startsWith('/backend/api/')) {
      // 目标后端服务器地址
      const BACKEND_URL = 'https://wenge9529.serv00.net';
      
      // 构造新的目标 URL
      // 例如: /backend/api/auth.php -> https://wenge9529.serv00.net/backend/api/auth.php
      const targetUrl = new URL(url.pathname + url.search, BACKEND_URL);

      // 创建一个新的 Headers 对象，复制原始请求的头信息
      const newHeaders = new Headers(request.headers);
      
      // 设置 Host 头，以确保 Serv00 服务器正确处理请求
      newHeaders.set('Host', new URL(BACKEND_URL).host);

      // 构造转发请求
      const newRequest = new Request(targetUrl, {
        method: request.method,
        headers: newHeaders,
        body: request.body, // 直接传递 body
        redirect: 'follow'
      });
      
      // 发起请求并返回响应
      return fetch(newRequest);
    }

    // 对于所有其他非 API 请求，由 Pages 正常提供静态资源
    return env.ASSETS.fetch(request);
  }
};
