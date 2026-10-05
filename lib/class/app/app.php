<?php

class app
{

	private static ?appCms $cms = null;

	private static ?appSite $site = null;

	private static ?appRequest $request = null;

	private static ?appRequest $referrer = null;

	private static ?appRequest $renderRequest = null;

	private static ?appRequest $reference = null;

	public static function cms(): appCms
	{
		return self::$cms ??= new appCms;
	}

	public static function site(): appSite
	{
		return self::$site ??= new appSite;
	}

	public static function request(): appRequest
	{
		return self::$request ??= new appRequest(http::requestUrl());
	}

	public static function referrer(): appRequest
	{
		return self::$referrer ??= new appRequest(http::referrer());
	}
	public static function renderRequest(): appRequest // not sure
	{
		return self::$renderRequest ??= new appRequest(http::request('renderurl'));
	}

	/**
	 * The state the client is showing, on a diff request — the half of the
	 * url before the tilde. NULL on an ordinary request.
	 *
	 * A full appRequest rather than a string, so the reference is readable
	 * the same way as the request itself: ->rawpath, ->modulePath, ->alias,
	 * and its own query through http::getUrlInfo(). That is what lets two
	 * cms windows on ?id=18 and ?id=20 be told apart.
	 *
	 * getUrlInfo() reads scheme and host unconditionally, so the stored
	 * path+query is made absolute again here.
	 */
	public static function reference(): ?appRequest
	{
		if (http::$referenceUrl === null) {
			return null;
		}
		return self::$reference ??= new appRequest(http::$hostUrl . http::$referenceUrl);
	}

	public static function user(): \Systopic\System\Auth\Identity
	{
		return \Systopic\System\Auth\Session::current();
	}
}
