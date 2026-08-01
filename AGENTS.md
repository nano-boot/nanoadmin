# AGENTS

这是 `nanoadmin` 插件源码仓库。

所有 AI 编码代理必须遵守以下规则：

1. 所有业务代码修改都在本仓库内完成。
2. 实际源码路径以 `src/plugin/nanoadmin/` 为准。
3. 不要去修改宿主项目中的发布副本：
   - `/www/wwwroot/mine/the-admin/nanoadmin-service/plugin/nanoadmin`
4. 宿主项目中的 `plugin/nanoadmin/...` 仅用于运行验证、联调和对照，不是可直接提交的源码。
5. 若修改未在宿主项目生效，应检查插件安装、复制、同步流程，而不是直接改宿主副本。

## 配套规则（位于宿主项目 nanoadmin-service）

| 文档 | 用途 |
|---|---|
| `nanoadmin-service/.cursor/rules/webman-nanoadmin-architecture.mdc` | 架构职责边界（主项目 vs 插件） |
| `nanoadmin-service/.cursor/rules/cache-implementation.mdc` | 缓存实现规范（webman/think-cache） |
| `nanoadmin-service/.cursor/rules/tree-search-development.mdc` | 树形 + 多条件搜索接口（部门 / 菜单 / 分类） |
| `nanoadmin-service/.cursor/skills/admin-crud/SKILL.md` | 后端 CRUD 模块完整开发流程（含 Validator 写法、按场景切换必填规则） |
| `nanoadmin-service/.cursor/skills/openapi-annotation-routes/SKILL.md` | OpenAPI 注解路由 + 控制器写法 |
| `nanoadmin-service/.cursor/skills/authorization/SKILL.md` | 权限声明开发技能 |

> ⚠️ 开发新模块时**先读 admin-crud skill**，涉及到树形搜索时**必读**对应的 tree-search rule，**不要自主发挥**。
