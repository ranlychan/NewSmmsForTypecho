# NewSmmsForTypecho ![Typecho Version Support](https://img.shields.io/badge/Typecho-v1.1%20(17.10.30)%20tested-brightgreen) ![GitHub release (latest by date)](https://img.shields.io/github/v/release/ranlychan/NewSmmsForTypecho) ![GitHub all releases](https://img.shields.io/github/downloads/ranlychan/NewSmmsForTypecho/total) 

![插件的设置界面](https://s2.loli.net/2023/03/03/cJw1gfAmdoVEaWP.png)

## 为什么开发
- 由于smms的图床最近针对大陆设置了专门的访问域名smms.app（真的良心厂商），原来的sm.ms似乎不太可用了。
- [SmmsForTypecho](https://github.com/gogobody/SmmsForTypecho) 的插件似乎没有在维护了，bug也多，所以就没有去拉仓库改了，而是自己拉代码大改一下。
- 一个原因就是我也想学习和入门一下Typecho的插件开发。

## 有什么功能特色
- **分离上传**：基于原生上传，可实现附件传本地，图片传图床（自动上传图床的图片扩展名可自定义，但若smms不支持则会上传失败）
- **服务切换**：支持smms的国内（smms.app）和国外（sm.ms）两个服务域名的切换。
- **简单易用**：启用插件后只需填入从smms获取的API Token即可开始在原生附件上传中使用。

## 有什么后续计划
这是一个Typecho插件的开发试验，用于学习和探索Typecho插件开发流程与技巧，了解Typecho程序与数据结构的小项目。后续将基于这些学习，开发一款针对Typecho的图片功能做出若干优化的插件。欢迎关注，详细请见我的博文[https://ranlychan.top/archives/538.html](https://ranlychan.top/archives/538.html)

> 博文地址：https://ranlychan.top/archives/552.html
