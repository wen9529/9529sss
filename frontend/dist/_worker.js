export default {
  async fetch(request, env) {
    const url = new URL(request.url);

    // 拦截 API 请求
    if (url.pathname.startsWith('/api/')) {
      // 修正：后端 URL 指向正确的域名
      const BACKEND_HOST = 'https://9525.ip-ddns.com'; 
      // 修正：移除了错误的 /backend 前缀
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

      try {
        const response = await fetch(newUrl.toString(), init);
        
        // 检查后端是否返回了错误
        if (response.status === 502) {
           return new Response(JSON.stringify({
             status: 'error', 
             message: '后端服务器返回了 502，请检查 Serv00 的 PHP 运行状态'
           }), { status: 502, headers: { 'Content-Type': 'application/json' } });
        }

        return response;
      } catch (err) {
        return new Response(JSON.stringify({
          status: 'error',
          message: 'Worker 转发失败: ' + err.message
        }), { status: 500, headers: { 'Content-Type': 'application/json' } });
      }
    }

    // 否则返回前端静态资源
    return env.ASSETS.fetch(request);
  },
};
