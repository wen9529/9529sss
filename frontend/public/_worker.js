export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // 拦截 API 请求
    if (url.pathname.startsWith('/api/')) {
      // 指向你的 Serv00 后端服务器地址
      const BACKEND_HOST = 'https://9525.ip-ddns.com';
      
      // 直接使用原始路径，不再添加 /backend 前缀
      const targetPath = url.pathname;
      const newUrl = new URL(targetPath, BACKEND_HOST);
      newUrl.search = url.search;

      // 构造转发请求
      const init = {
        method: request.method,
        headers: new Headers(request.headers),
        redirect: 'follow'
      };

      // 只有非 GET/HEAD 请求才携带 body
      if (request.method !== 'GET' && request.method !== 'HEAD') {
        init.body = await request.clone().arrayBuffer();
      }
      
      const newRequest = new Request(newUrl, init);

      return fetch(newRequest);
    }

    // 对于非 API 请求，继续由 Cloudflare Pages 提供静态资源
    return env.ASSETS.fetch(request);
  }
};
