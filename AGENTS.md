# AGENTS

这是 `nanoadmin` 插件源码仓库。

所有 AI 编码代理必须遵守以下规则：

1. 所有业务代码修改都在本仓库内完成。
2. 实际源码路径以 `src/plugin/nanoadmin/` 为准。
3. 不要去修改宿主项目中的发布副本：
   - `/www/wwwroot/mine/the-admin/the-admin-service/plugin/nanoadmin`
4. 宿主项目中的 `plugin/nanoadmin/...` 仅用于运行验证、联调和对照，不是可直接提交的源码。
5. 若修改未在宿主项目生效，应检查插件安装、复制、同步流程，而不是直接改宿主副本。

配套规则请阅读：

- `/www/wwwroot/mine/the-admin/the-admin-service/.codex/rules/webman-nanoadmin-architecture.md`
